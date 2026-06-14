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

use pocketmine\block\utils\TripwireHookLogic;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use function count;

class Tripwire extends Flowable{
	private const RECHECK_DELAY_TICKS = 10;

	protected bool $triggered = false;
	protected bool $suspended = false; //unclear usage, makes hitbox bigger if set
	protected bool $connected = false;
	protected bool $disarmed = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->bool($this->triggered);
		$w->bool($this->suspended);
		$w->bool($this->connected);
		$w->bool($this->disarmed);
	}

	public function isTriggered() : bool{ return $this->triggered; }

	/** @return $this */
	public function setTriggered(bool $triggered) : self{
		$this->triggered = $triggered;
		return $this;
	}

	public function isSuspended() : bool{ return $this->suspended; }

	/** @return $this */
	public function setSuspended(bool $suspended) : self{
		$this->suspended = $suspended;
		return $this;
	}

	public function isConnected() : bool{ return $this->connected; }

	/** @return $this */
	public function setConnected(bool $connected) : self{
		$this->connected = $connected;
		return $this;
	}

	public function isDisarmed() : bool{ return $this->disarmed; }

	/** @return $this */
	public function setDisarmed(bool $disarmed) : self{
		$this->disarmed = $disarmed;
		return $this;
	}

	public function hasEntityCollision() : bool{
		return true;
	}

	public function onEntityInside(Entity $entity) : bool{
		if(!$this->triggered){
			$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 0);
		}
		return true;
	}

	private function getActivationBox() : AxisAlignedBB{
		return AxisAlignedBB::one()->offset($this->position->x, $this->position->y, $this->position->z);
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$triggered = count($world->getNearbyEntities($this->getActivationBox())) > 0;
		if($triggered !== $this->triggered){
			$this->triggered = $triggered;
			$world->setBlock($this->position, $this);
			$this->notifyHooks();
		}
		if($triggered){
			$world->scheduleDelayedBlockUpdate($this->position, self::RECHECK_DELAY_TICKS);
		}
	}

	public function onPostPlace() : void{
		$this->notifyHooks();
	}

	public function onNearbyBlockChange() : void{
		$this->notifyHooks();
	}

	/**
	 * Walks the string line out to the hook at each end of this wire's row and asks each to recompute its circuit, so a
	 * step on (or change to) any string updates the redstone output of the hooks.
	 */
	private function notifyHooks() : void{
		$world = $this->position->getWorld();
		foreach(Facing::HORIZONTAL as $direction){
			for($d = 1; $d <= TripwireHookLogic::MAX_DISTANCE; ++$d){
				$block = $world->getBlock($this->position->getSide($direction, $d));
				if($block instanceof TripwireHook){
					if($block->getFacing() === Facing::opposite($direction)){
						$block->recalculateState();
					}
					break;
				}
				if(!($block instanceof Tripwire)){
					break;
				}
			}
		}
	}

	public function asItem() : Item{
		return VanillaItems::STRING();
	}
}
