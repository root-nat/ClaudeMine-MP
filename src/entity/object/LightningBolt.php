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

namespace pocketmine\entity\object;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\sound\ExplodeSound;
use pocketmine\world\sound\ThunderSound;

class LightningBolt extends Entity implements NeverSavedWithChunkEntity{
	private const LIFETIME_TICKS = 20;

	public const BASE_DAMAGE = 5;
	public const DAMAGE_RADIUS = 3;
	public const FIRE_SECONDS = 8;

	private bool $createsFire = false;

	public function __construct(Location $location, ?CompoundTag $nbt = null){
		parent::__construct($location, $nbt);
	}

	public static function getNetworkTypeId() : string{ return EntityIds::LIGHTNING_BOLT; }

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.001, 0.001); }

	protected function getInitialDragMultiplier() : float{ return 0.0; }

	protected function getInitialGravity() : float{ return 0.0; }

	public function isCreatingFire() : bool{
		return $this->createsFire;
	}

	public function setCreatesFire(bool $createsFire) : void{
		$this->createsFire = $createsFire;
	}

	protected function onFirstUpdate(int $currentTick) : void{
		parent::onFirstUpdate($currentTick);

		$this->broadcastSound(new ThunderSound());
		$this->broadcastSound(new ExplodeSound());

		$world = $this->getWorld();

		foreach($world->getNearbyEntities($this->boundingBox->expandedCopy(self::DAMAGE_RADIUS, self::DAMAGE_RADIUS * 2, self::DAMAGE_RADIUS), $this) as $entity){
			if($entity instanceof Living){
				$ev = new EntityDamageByEntityEvent($this, $entity, EntityDamageEvent::CAUSE_LIGHTNING, self::BASE_DAMAGE);
				$entity->attack($ev);
				if(!$ev->isCancelled()){
					$entity->setOnFire(self::FIRE_SECONDS);
				}
			}
		}

		if($this->createsFire){
			$pos = $this->location->floor();
			if($world->isInWorld($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ())){
				$block = $world->getBlock($pos);
				if($block->getTypeId() === BlockTypeIds::AIR && $world->getBlock($pos->down())->isSolid()){
					$world->setBlock($pos, VanillaBlocks::FIRE());
				}
			}
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		if($this->ticksLived >= self::LIFETIME_TICKS){
			$this->flagForDespawn();
		}
		return parent::entityBaseTick($tickDiff);
	}

	public function attack(EntityDamageEvent $source) : void{
		$source->cancel();
	}
}
