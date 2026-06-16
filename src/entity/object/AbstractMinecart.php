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

use pocketmine\block\ActivatorRail;
use pocketmine\block\BaseRail;
use pocketmine\block\PoweredRail;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\RideableEntity;
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
	/** Horizontal impulse applied to push an overlapping entity (and the cart itself) apart each tick they collide. */
	private const COLLISION_PUSH = 0.1;

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
			if($rail instanceof ActivatorRail){
				$this->onActivatorRail($rail->isPowered());
			}
			$hasUpdate = true;
		}

		$this->pushCollidingEntities();

		return $hasUpdate || abs($this->motion->x) > self::MOTION_THRESHOLD || abs($this->motion->z) > self::MOTION_THRESHOLD;
	}

	/**
	 * Soft entity collision: pushes any living entity or other minecart overlapping this cart apart from it (and nudges
	 * the cart the opposite way), so a player can bump and shove the cart and the cart shoves things it runs into. The
	 * cart's own rider is left alone.
	 */
	private function pushCollidingEntities() : void{
		$rider = $this instanceof RideableEntity ? $this->getRider() : null;
		foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(0.2, 0.0, 0.2), $this) as $entity){
			if($entity === $rider || (!($entity instanceof Living) && !($entity instanceof AbstractMinecart))){
				continue;
			}
			$ePos = $entity->getPosition();
			$dx = $ePos->x - $this->location->x;
			$dz = $ePos->z - $this->location->z;
			$distSq = ($dx * $dx) + ($dz * $dz);
			if($distSq < 1e-4){
				continue;
			}
			$push = self::COLLISION_PUSH / sqrt($distSq);
			$entity->addMotion($dx * $push, 0.0, $dz * $push); //always shove the other thing apart from the cart
			if($entity instanceof Living){
				$motion = $entity->getMotion();
				if((($motion->x * $motion->x) + ($motion->z * $motion->z)) > self::MOTION_THRESHOLD){
					//only a MOVING entity (someone walking into the cart) shoves it back; an idle one standing nearby must not
					//drive a parked cart off down the track. Another minecart is NOT self-pushed here - its own collision loop
					//already pushes this cart back, so doing it here too would double the impulse.
					$this->addMotion(-$dx * $push, 0.0, -$dz * $push);
				}
			}
		}
	}

	/**
	 * Called each tick the cart rides an activator rail. Subclasses react to a powered one - a TNT minecart primes; a
	 * rideable cart would eject its rider. The plain cart does nothing.
	 */
	protected function onActivatorRail(bool $powered) : void{
		//NOOP
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
