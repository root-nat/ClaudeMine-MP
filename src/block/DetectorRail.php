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
use pocketmine\math\AxisAlignedBB;

class DetectorRail extends StraightOnlyRail{
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
		return true;
	}

	public function onEntityInside(Entity $entity) : bool{
		if(!$this->activated){
			$this->activated = true;
			$this->position->getWorld()->setBlock($this->position, $this);
		}
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 20);
		return false;
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$pos = $this->position;
		$bb = new AxisAlignedBB($pos->x, $pos->y, $pos->z, $pos->x + 1, $pos->y + 1, $pos->z + 1);
		if(!empty($world->getNearbyEntities($bb))){
			$world->scheduleDelayedBlockUpdate($this->position, 20);
			return;
		}
		if($this->activated){
			$this->activated = false;
			$world->setBlock($this->position, $this);
		}
	}

	public function getWeakRedstonePower(int $face) : int{
		return $this->activated ? 15 : 0;
	}
}
