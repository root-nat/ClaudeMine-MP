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
use function count;

/**
 * An ordered sequence of grid waypoints produced by the pathfinder, with a cursor tracking the mob's progress.
 * Pure data; consumed by {@link PathNavigator}.
 */
final class Path{

	/**
	 * @param int[][] $points list of [x, y, z] grid coordinates, start-to-target
	 * @phpstan-param list<array{int, int, int}> $points
	 */
	public function __construct(
		private array $points,
		private int $cursor = 0
	){}

	public function isEmpty() : bool{
		return count($this->points) === 0;
	}

	public function count() : int{
		return count($this->points);
	}

	/**
	 * @return int[][]
	 * @phpstan-return list<array{int, int, int}>
	 */
	public function getPoints() : array{
		return $this->points;
	}

	public function getCursor() : int{
		return $this->cursor;
	}

	public function isFinished() : bool{
		return $this->cursor >= count($this->points);
	}

	/**
	 * Returns the waypoint currently being walked towards as a centred Vector3, or null if the path is finished.
	 */
	public function currentWaypoint() : ?Vector3{
		if($this->isFinished()){
			return null;
		}
		[$x, $y, $z] = $this->points[$this->cursor];
		return new Vector3($x + 0.5, $y, $z + 0.5);
	}

	/**
	 * @return int[]|null
	 * @phpstan-return array{int, int, int}|null
	 */
	public function currentNode() : ?array{
		return $this->points[$this->cursor] ?? null;
	}

	/**
	 * @return int[]|null
	 * @phpstan-return array{int, int, int}|null
	 */
	public function nextNode() : ?array{
		return $this->points[$this->cursor + 1] ?? null;
	}

	public function advance() : void{
		++$this->cursor;
	}

	/**
	 * Returns the final waypoint as a centred Vector3, or null if the path is empty.
	 */
	public function target() : ?Vector3{
		if(count($this->points) === 0){
			return null;
		}
		[$x, $y, $z] = $this->points[count($this->points) - 1];
		return new Vector3($x + 0.5, $y, $z + 0.5);
	}
}
