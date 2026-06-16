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

use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Entity;
use pocketmine\entity\object\AbstractMinecart;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;

class DetectorRail extends StraightOnlyRail{
	/** How long the rail keeps emitting after a minecart was last seen, re-checked while one is present. */
	private const DEACTIVATE_DELAY_TICKS = 4;

	protected bool $activated = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		parent::describeBlockOnlyState($w);
		$w->bool($this->activated);
	}

	public function isActivated() : bool{ return $this->activated; }

	/** @return $this */
	public function setActivated(bool $activated) : self{
		$this->activated = $activated;
		return $this;
	}

	public function hasEntityCollision() : bool{
		return true; //no collision box, but this makes onEntityInside fire for carts rolling over the rail
	}

	public function onEntityInside(Entity $entity) : bool{
		if($entity instanceof AbstractMinecart && !$this->activated){
			$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 0);
		}
		return true;
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$hasCart = false;
		foreach($world->getNearbyEntities(AxisAlignedBB::one()->offset($this->position->x, $this->position->y, $this->position->z)) as $entity){
			if($entity instanceof AbstractMinecart){
				$hasCart = true;
				break;
			}
		}

		if($hasCart !== $this->activated){
			$this->activated = $hasCart;
			$world->setBlock($this->position, $this);
			//detector rails power the block below (and adjacent) - refresh those neighbours' redstone
			$world->notifyNeighbourBlockUpdate($this->position->down());
			foreach(Facing::HORIZONTAL as $face){
				$world->notifyNeighbourBlockUpdate($this->position->getSide($face));
			}
		}
		if($hasCart){
			$world->scheduleDelayedBlockUpdate($this->position, self::DEACTIVATE_DELAY_TICKS);
		}
	}

	public function getWeakRedstonePower(int $face) : int{
		return $this->activated ? 15 : 0;
	}

	public function getStrongRedstonePower(int $face) : int{
		//like a pressure plate, a detector rail STRONGLY powers only the block directly below it (weak power goes to all
		//neighbours); strong-powering every face would wrongly let an adjacent block re-emit to dust sitting on it
		return $face === Facing::DOWN ? $this->getWeakRedstonePower($face) : 0;
	}
}
