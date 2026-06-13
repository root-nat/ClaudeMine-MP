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

use pocketmine\block\BaseRail;
use pocketmine\block\PoweredRail;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use function abs;
use function sqrt;

abstract class AbstractMinecart extends Entity{

	private const MAX_SPEED = 0.4;
	private const POWERED_RAIL_ACCEL = 0.06;
	private const POWERED_RAIL_BRAKE = 0.5;
	private const ROLL_FRICTION = 0.97;

	protected int $travelDirection = Facing::NORTH;

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.7, 0.98); }

	protected function getInitialDragMultiplier() : float{ return 0.05; }

	protected function getInitialGravity() : float{ return 0.04; }

	/**
	 * Returns the item that should be dropped (in addition to any contents) when the minecart is destroyed.
	 */
	abstract protected function getMinecartItem() : Item;

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->closed || !$this->isAlive()){
			return $hasUpdate;
		}

		$rail = $this->findRail();
		if($rail !== null){
			$this->moveAlongRail($rail);
			$hasUpdate = true;
		}

		return $hasUpdate || abs($this->motion->x) > self::MOTION_THRESHOLD || abs($this->motion->z) > self::MOTION_THRESHOLD;
	}

	private function findRail() : ?BaseRail{
		$world = $this->getWorld();
		$pos = $this->location;
		$here = $world->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		if($here instanceof BaseRail){
			return $here;
		}
		$below = $world->getBlockAt($pos->getFloorX(), $pos->getFloorY() - 1, $pos->getFloorZ());
		return $below instanceof BaseRail ? $below : null;
	}

	private function moveAlongRail(BaseRail $rail) : void{
		$connections = MinecartRailLogic::getConnectionsForShape($rail->getShape());
		if($connections === null){
			return;
		}

		$speed = sqrt($this->motion->x ** 2 + $this->motion->z ** 2);
		if($speed > self::MOTION_THRESHOLD){
			$this->travelDirection = MinecartRailLogic::getNextDirection($connections, $this->horizontalDirectionFromMotion());
		}elseif(!MinecartRailLogic::canTravel($connections, $this->travelDirection)){
			$this->travelDirection = $connections[0];
		}

		if($rail instanceof PoweredRail){
			if($rail->isPowered()){
				$speed += self::POWERED_RAIL_ACCEL;
			}else{
				$speed *= self::POWERED_RAIL_BRAKE;
			}
		}else{
			$speed *= self::ROLL_FRICTION;
		}

		if($speed > self::MAX_SPEED){
			$speed = self::MAX_SPEED;
		}

		$offset = Vector3::zero()->getSide($this->travelDirection);
		$yMotion = MinecartRailLogic::isAscending($rail->getShape()) ? $speed : 0.0;
		$this->motion = new Vector3($offset->x * $speed, $yMotion, $offset->z * $speed);
	}

	private function horizontalDirectionFromMotion() : int{
		if(abs($this->motion->x) >= abs($this->motion->z)){
			return $this->motion->x >= 0 ? Facing::EAST : Facing::WEST;
		}
		return $this->motion->z >= 0 ? Facing::SOUTH : Facing::NORTH;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if($source->isCancelled()){
			return;
		}
		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager instanceof Player && $damager->isCreative()){
				$this->destroyCart(false);
				return;
			}
		}
		if($this->getHealth() <= 0){
			$this->destroyCart(true);
		}
	}

	protected function destroyCart(bool $dropItems) : void{
		if($dropItems){
			$this->getWorld()->dropItem($this->location, $this->getMinecartItem());
			foreach($this->getContentDrops() as $drop){
				$this->getWorld()->dropItem($this->location, $drop);
			}
		}
		$this->flagForDespawn();
	}

	/**
	 * @return Item[]
	 */
	protected function getContentDrops() : array{
		return [];
	}

	public function getPickedItem() : ?Item{
		return $this->getMinecartItem();
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt("PMMPTravelDirection", $this->travelDirection);
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->travelDirection = $nbt->getInt("PMMPTravelDirection", Facing::NORTH);
	}
}
