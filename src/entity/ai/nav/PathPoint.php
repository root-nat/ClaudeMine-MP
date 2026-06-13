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

/**
 * A node in the A* open/closed set. Holds grid coordinates, accumulated cost g, heuristic h, total f and a back-pointer
 * to the parent node for path reconstruction. Pure value-with-mutable-cost object; no engine dependencies.
 */
final class PathPoint{

	public float $g = 0.0;
	public float $h = 0.0;
	public float $f = 0.0;
	public ?PathPoint $parent = null;
	public int $heapIndex = -1;
	public bool $closed = false;

	public function __construct(
		public readonly int $x,
		public readonly int $y,
		public readonly int $z
	){}

	public static function hashOf(int $x, int $y, int $z) : string{
		return $x . ":" . $y . ":" . $z;
	}

	public function hash() : string{
		return self::hashOf($this->x, $this->y, $this->z);
	}
}
