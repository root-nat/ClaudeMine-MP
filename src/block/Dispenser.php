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

use pocketmine\block\tile\Dispenser as TileDispenser;
use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\AnyFacingTrait;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Location;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\entity\projectile\Arrow as ArrowEntity;
use pocketmine\entity\projectile\Egg as EggEntity;
use pocketmine\entity\projectile\Snowball as SnowballEntity;
use pocketmine\entity\projectile\SplashPotion as SplashPotionEntity;
use pocketmine\inventory\Inventory;
use pocketmine\item\Arrow as ArrowItem;
use pocketmine\item\Egg as EggItem;
use pocketmine\item\FireCharge;
use pocketmine\item\Item;
use pocketmine\item\Snowball as SnowballItem;
use pocketmine\item\SplashPotion as SplashPotionItem;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\gamerule\GameRule;
use pocketmine\world\sound\BlazeShootSound;
use pocketmine\world\sound\IgniteSound;
use pocketmine\world\World;
use function array_keys;
use function count;
use function mt_rand;

class Dispenser extends Opaque implements AnyFacing, PoweredByRedstone{
	use AnyFacingTrait;
	use PoweredByRedstoneTrait;

	private const DISPENSE_DELAY_TICKS = 4;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
		$w->bool($this->powered);
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			$this->facing = Facing::opposite($player->getHorizontalFacing());
			$pitch = $player->getLocation()->getPitch();
			if($pitch > 48){
				$this->facing = Facing::UP;
			}elseif($pitch < -48){
				$this->facing = Facing::DOWN;
			}
		}
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($player !== null){
			$tile = $this->position->getWorld()->getTile($this->position);
			if($tile instanceof TileDispenser){
				$player->setCurrentWindow($tile->getInventory());
			}
			return true;
		}
		return false;
	}

	public function onNearbyBlockChange() : void{
		$world = $this->position->getWorld();
		$powered = $this->isReceivingRedstonePower();
		if($powered !== $this->powered){
			$this->powered = $powered;
			$world->setBlock($this->position, $this);
			if($powered){
				$world->scheduleDelayedBlockUpdate($this->position, self::DISPENSE_DELAY_TICKS);
			}
		}
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$tile = $world->getTile($this->position);
		if($tile instanceof TileDispenser){
			$this->executeDispense($world, $tile);
		}
	}

	protected function executeDispense(World $world, TileDispenser $tile) : void{
		$single = $this->popRandomItem($tile->getInventory());
		if($single === null){
			return;
		}
		if(!$this->dispenseSpecial($world, $single)){
			$this->ejectItem($world, $single);
		}
	}

	protected function popRandomItem(Inventory $inventory) : ?Item{
		$candidates = [];
		foreach($inventory->getContents() as $slot => $item){
			$candidates[] = $slot;
		}
		if(count($candidates) === 0){
			return null;
		}
		$slot = $candidates[mt_rand(0, count($candidates) - 1)];
		$item = $inventory->getItem($slot);
		$single = $item->pop();
		$inventory->setItem($slot, $item->getCount() > 0 ? $item : VanillaItems::AIR());
		return $single;
	}

	protected function getDispensePosition() : Vector3{
		return $this->position->add(0.5, 0.5, 0.5)->addVector(Vector3::zero()->getSide($this->facing)->multiply(0.7));
	}

	protected function ejectItem(World $world, Item $item) : void{
		$motion = Vector3::zero()->getSide($this->facing)->multiply(0.3)->add(
			(mt_rand(-10, 10) / 100),
			0.1,
			(mt_rand(-10, 10) / 100)
		);
		$world->dropItem($this->getDispensePosition(), $item, $motion, 40);
	}

	private function dispenseSpecial(World $world, Item $item) : bool{
		$dispensePos = $this->getDispensePosition();
		$direction = Vector3::zero()->getSide($this->facing);
		$location = Location::fromObject($dispensePos, $world);

		if($item instanceof ArrowItem){
			$entity = new ArrowEntity($location, null, false);
			$entity->setMotion($direction->multiply(1.5));
			$entity->spawnToAll();
			$world->addSound($this->position, new BlazeShootSound());
			return true;
		}
		if($item instanceof SnowballItem){
			$entity = new SnowballEntity($location, null);
			$entity->setMotion($direction->multiply(1.1));
			$entity->spawnToAll();
			$world->addSound($this->position, new BlazeShootSound());
			return true;
		}
		if($item instanceof EggItem){
			$entity = new EggEntity($location, null);
			$entity->setMotion($direction->multiply(1.1));
			$entity->spawnToAll();
			$world->addSound($this->position, new BlazeShootSound());
			return true;
		}
		if($item instanceof SplashPotionItem){
			$entity = new SplashPotionEntity($location, null, $item->getType());
			$entity->setMotion($direction->multiply(1.1));
			$entity->spawnToAll();
			$world->addSound($this->position, new BlazeShootSound());
			return true;
		}
		if($item instanceof FireCharge){
			$target = $this->getSide($this->facing);
			if($target->getTypeId() === BlockTypeIds::AIR){
				$world->setBlock($target->getPosition(), VanillaBlocks::FIRE());
				$world->addSound($target->getPosition()->add(0.5, 0.5, 0.5), new BlazeShootSound());
				return true;
			}
			return false;
		}
		if($item->getBlock()->getTypeId() === BlockTypeIds::TNT){
			if(!$world->getGameRules()->getBool(GameRule::TNT_EXPLODES)){
				return false;
			}
			$tnt = new PrimedTNT(Location::fromObject($this->getSide($this->facing)->getPosition()->add(0.5, 0, 0.5), $world));
			$tnt->setFuse(80);
			$tnt->spawnToAll();
			$tnt->broadcastSound(new IgniteSound());
			return true;
		}

		return false;
	}
}
