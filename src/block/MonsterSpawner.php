<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\tile\MonsterSpawner as TileMonsterSpawner;
use pocketmine\block\utils\SupportType;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Living;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\player\Player;
use pocketmine\world\Dimension;
use pocketmine\world\World;
use function count;
use function mt_rand;

class MonsterSpawner extends Transparent{

	private const UNCONFIGURED_ENTITY_TYPE = ":"; //the tile's placeholder until an entity type is assigned

	private const TICK_PERIOD_ACTIVE = 10; //ticks between spawn ticks while a player is in range
	private const TICK_PERIOD_IDLE = 60; //slower poll while no player is in range, so the loop stays cheap but responsive

	/** @var string[] picked at random for a spawner generated outside the Nether (classic dungeon mobs) */
	private const OVERWORLD_DEFAULT_ENTITIES = ["minecraft:zombie", "minecraft:skeleton", "minecraft:spider"];

	public function getDropsForCompatibleTool(Item $item) : array{
		return [];
	}

	protected function getXpDropAmount() : int{
		return mt_rand(15, 43);
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	public function ticksRandomly() : bool{
		//a spawner placed by the world generator never runs through onPostPlace, so a random tick is what first wakes it;
		//once woken the scheduled-update loop keeps it ticking
		return true;
	}

	public function onRandomTick() : void{
		//the position-keyed scheduled-update queue de-dups, so kicking the loop here can never stack a second loop
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 1);
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$tile = $this->getOrCreateTile($world);
		if($tile === null){
			return;
		}

		if($world->getNearestEntity($this->position, $tile->getRequiredPlayerRange(), Player::class) === null){
			$world->scheduleDelayedBlockUpdate($this->position, self::TICK_PERIOD_IDLE);
			return;
		}

		$remaining = $tile->getSpawnDelay() - self::TICK_PERIOD_ACTIVE;
		if($remaining > 0){
			$tile->setSpawnDelay($remaining);
		}else{
			$this->trySpawn($world, $tile);
			$min = $tile->getMinSpawnDelay();
			$max = $tile->getMaxSpawnDelay();
			$tile->setSpawnDelay($max > $min ? mt_rand($min, $max) : $min);
		}
		$world->scheduleDelayedBlockUpdate($this->position, self::TICK_PERIOD_ACTIVE);
	}

	private function getOrCreateTile(World $world) : ?TileMonsterSpawner{
		$tile = $world->getTile($this->position);
		if($tile instanceof TileMonsterSpawner){
			$this->ensureConfigured($world, $tile);
			return $tile;
		}
		if($tile !== null){
			return null; //another tile somehow occupies this position
		}
		//a generator-placed spawner has no tile yet (it never went through World::setBlock): create and register one
		$tile = new TileMonsterSpawner($world, $this->position->asVector3());
		$this->ensureConfigured($world, $tile);
		$world->addTile($tile);
		return $tile;
	}

	private function ensureConfigured(World $world, TileMonsterSpawner $tile) : void{
		$id = $tile->getEntityTypeId();
		if($id === "" || $id === self::UNCONFIGURED_ENTITY_TYPE){
			$tile->setEntityTypeId($world->getDimension() === Dimension::NETHER ?
				"minecraft:blaze" :
				self::OVERWORLD_DEFAULT_ENTITIES[mt_rand(0, count(self::OVERWORLD_DEFAULT_ENTITIES) - 1)]);
		}
	}

	private function trySpawn(World $world, TileMonsterSpawner $tile) : void{
		$entityType = $tile->getEntityTypeId();
		if($entityType === "" || $entityType === self::UNCONFIGURED_ENTITY_TYPE){
			return;
		}
		$range = $tile->getSpawnRange();

		$bb = new AxisAlignedBB(
			$this->position->x - $range, $this->position->y - $range, $this->position->z - $range,
			$this->position->x + $range + 1, $this->position->y + $range + 1, $this->position->z + $range + 1
		);
		$nearby = 0;
		foreach($world->getNearbyEntities($bb) as $entity){
			if($entity instanceof Living && !($entity instanceof Player)){
				++$nearby;
			}
		}
		if($nearby >= $tile->getMaxNearbyEntities()){
			return;
		}

		for($i = 0; $i < $tile->getSpawnPerAttempt(); ++$i){
			$x = $this->position->getFloorX() + mt_rand(-$range, $range);
			$y = $this->position->getFloorY() + mt_rand(-1, 1);
			$z = $this->position->getFloorZ() + mt_rand(-$range, $range);
			if(!$this->isValidSpawnCell($world, $x, $y, $z)){
				continue;
			}
			$this->spawnEntity($world, $entityType, $x + 0.5, (float) $y, $z + 0.5);
		}
	}

	private function isValidSpawnCell(World $world, int $x, int $y, int $z) : bool{
		//two clear cells with a solid floor below, so mobs don't suffocate in walls or fall through the bridge
		return !$world->getBlockAt($x, $y, $z)->isSolid()
			&& !$world->getBlockAt($x, $y + 1, $z)->isSolid()
			&& $world->getBlockAt($x, $y - 1, $z)->isSolid();
	}

	private function spawnEntity(World $world, string $entityType, float $x, float $y, float $z) : void{
		$nbt = CompoundTag::create()
			->setString(EntityFactory::TAG_IDENTIFIER, $entityType)
			->setTag(Entity::TAG_POS, new ListTag([new DoubleTag($x), new DoubleTag($y), new DoubleTag($z)]))
			->setTag(Entity::TAG_ROTATION, new ListTag([new FloatTag((float) mt_rand(0, 359)), new FloatTag(0.0)]));
		$entity = EntityFactory::getInstance()->createFromData($world, $nbt);
		if($entity !== null){
			$entity->spawnToAll();
		}
	}
}
