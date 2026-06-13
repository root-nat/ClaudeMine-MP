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

use pocketmine\block\tile\Target as TileTarget;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Projectile;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\RayTraceResult;
use function max;
use function min;
use function round;
use function sqrt;

class Target extends Opaque{

	public function onProjectileHit(Projectile $projectile, RayTraceResult $hitResult) : void{
		$world = $this->position->getWorld();
		$tile = $world->getTile($this->position);
		if(!$tile instanceof TileTarget){
			return;
		}

		$hit = $hitResult->getHitVector()->subtract($this->position->x + 0.5, $this->position->y + 0.5, $this->position->z + 0.5);
		$distance = match(Facing::axis($hitResult->getHitFace())){
			Axis::X => sqrt($hit->y ** 2 + $hit->z ** 2),
			Axis::Y => sqrt($hit->x ** 2 + $hit->z ** 2),
			default => sqrt($hit->x ** 2 + $hit->y ** 2)
		};

		$signal = max(1, (int) round(15 * (1 - min(1.0, $distance * 2))));
		$tile->setOutputSignal($signal);

		$world->scheduleDelayedBlockUpdate($this->position, $projectile instanceof Arrow ? 20 : 8);
		$world->notifyNeighbourBlockUpdate($this->position);
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$tile = $world->getTile($this->position);
		if($tile instanceof TileTarget && $tile->getOutputSignal() > 0){
			$tile->setOutputSignal(0);
			$world->notifyNeighbourBlockUpdate($this->position);
		}
	}

	public function getWeakRedstonePower(int $face) : int{
		$tile = $this->position->getWorld()->getTile($this->position);
		return $tile instanceof TileTarget ? $tile->getOutputSignal() : 0;
	}

	public function isRedstoneConductor() : bool{
		return false;
	}

	public function getFlameEncouragement() : int{
		return 15;
	}

	public function getFlammability() : int{
		return 20;
	}
}
