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

namespace pocketmine\entity\ai\nav;

use pocketmine\math\Vector3;
use function sqrt;

/**
 * Consumes a {@link Path} and the mob's current position each tick, emitting a {@link MoveIntent}. Advances the path
 * cursor on arrival at each waypoint, raises the jump flag when the next waypoint is higher, and requests a re-path
 * when the mob makes no progress for too long. Pure: takes a Vector3, returns an intent, never touches an Entity.
 */
final class PathNavigator{
	private const ARRIVAL_RADIUS = 0.55;
	private const STUCK_EPSILON = 0.01;
	private const STUCK_TICK_LIMIT = 40;

	private ?Path $path = null;
	private float $lastDistanceToWaypoint = PHP_FLOAT_MAX;
	private int $noProgressTicks = 0;

	public function setPath(?Path $path) : void{
		$this->path = $path;
		$this->lastDistanceToWaypoint = PHP_FLOAT_MAX;
		$this->noProgressTicks = 0;
	}

	public function getPath() : ?Path{
		return $this->path;
	}

	public function isIdle() : bool{
		return $this->path === null || $this->path->isFinished();
	}

	public function tick(Vector3 $position) : MoveIntent{
		if($this->path === null || $this->path->isFinished()){
			return MoveIntent::idle();
		}

		$waypoint = $this->path->currentWaypoint();
		if($waypoint === null){
			return MoveIntent::idle();
		}

		$dx = $waypoint->x - $position->x;
		$dz = $waypoint->z - $position->z;
		$horizontalDistance = sqrt(($dx * $dx) + ($dz * $dz));

		if($horizontalDistance <= self::ARRIVAL_RADIUS){
			$this->path->advance();
			$this->lastDistanceToWaypoint = PHP_FLOAT_MAX;
			$this->noProgressTicks = 0;
			if($this->path->isFinished()){
				return MoveIntent::idle();
			}
			$waypoint = $this->path->currentWaypoint();
			if($waypoint === null){
				return MoveIntent::idle();
			}
			$dx = $waypoint->x - $position->x;
			$dz = $waypoint->z - $position->z;
			$horizontalDistance = sqrt(($dx * $dx) + ($dz * $dz));
		}

		//stuck detection: if we stop getting closer to the current waypoint, ask for a re-path
		if($horizontalDistance >= $this->lastDistanceToWaypoint - self::STUCK_EPSILON){
			if(++$this->noProgressTicks >= self::STUCK_TICK_LIMIT){
				return MoveIntent::repath();
			}
		}else{
			$this->noProgressTicks = 0;
		}
		$this->lastDistanceToWaypoint = $horizontalDistance;

		$length = $horizontalDistance > 1e-4 ? $horizontalDistance : 1.0;
		$dirX = $dx / $length;
		$dirZ = $dz / $length;

		$jump = $waypoint->y > $position->y + 0.5;

		return new MoveIntent($dirX, $dirZ, $jump, false, false);
	}
}
