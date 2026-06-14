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

use pocketmine\block\utils\SculkSensorLogic;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Living;
use pocketmine\math\AxisAlignedBB;

/**
 * Detects a nearby vibration (a moving creature) and emits a redstone signal for a short window, then briefly cools down.
 * The phase machine + timings live in {@link SculkSensorLogic}; this resolves the live vibration and drives the redstone.
 */
class SculkSensor extends Transparent{

	private const VIBRATION_MOTION_THRESHOLD = 0.003;

	protected int $phase = SculkSensorLogic::PHASE_INACTIVE;
	protected bool $powered = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(SculkSensorLogic::PHASE_INACTIVE, SculkSensorLogic::PHASE_COOLDOWN, $this->phase);
		$w->bool($this->powered);
	}

	public function getPhase() : int{ return $this->phase; }

	/** @return $this */
	public function setPhase(int $phase) : self{
		$this->phase = $phase;
		return $this;
	}

	public function isPowered() : bool{ return $this->powered; }

	/** @return $this */
	public function setPowered(bool $powered) : self{
		$this->powered = $powered;
		return $this;
	}

	public function onPostPlace() : void{
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, SculkSensorLogic::SCAN_INTERVAL);
	}

	public function onNearbyBlockChange() : void{
		//keep the detection loop alive (and resume it if the chunk was reloaded with the sensor dormant)
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, SculkSensorLogic::SCAN_INTERVAL);
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$vibration = $this->phase === SculkSensorLogic::PHASE_INACTIVE && $this->detectVibration();
		[$newPhase, $powered, $delay] = SculkSensorLogic::nextState($this->phase, $vibration);

		if($newPhase !== $this->phase || $powered !== $this->powered){
			$poweredChanged = $powered !== $this->powered;
			$this->phase = $newPhase;
			$this->powered = $powered;
			$world->setBlock($this->position, $this);
			if($poweredChanged){
				$world->notifyNeighbourBlockUpdate($this->position);
			}
		}

		$world->scheduleDelayedBlockUpdate($this->position, $delay);
	}

	private function detectVibration() : bool{
		$world = $this->position->getWorld();
		$r = SculkSensorLogic::VIBRATION_RANGE;
		$box = new AxisAlignedBB(
			$this->position->x - $r, $this->position->y - $r, $this->position->z - $r,
			$this->position->x + $r + 1, $this->position->y + $r + 1, $this->position->z + $r + 1
		);
		foreach($world->getNearbyEntities($box) as $entity){
			if($entity instanceof Living && $entity->getMotion()->lengthSquared() > self::VIBRATION_MOTION_THRESHOLD){
				return true;
			}
		}
		return false;
	}

	public function getWeakRedstonePower(int $face) : int{
		return $this->powered ? 15 : 0;
	}

	public function getStrongRedstonePower(int $face) : int{
		return $this->powered ? 15 : 0;
	}
}
