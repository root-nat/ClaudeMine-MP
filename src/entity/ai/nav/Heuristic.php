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

use function abs;
use function max;
use function min;
use function sqrt;

final class Heuristic{
	public const ORTHOGONAL_COST = 1.0;
	public const DIAGONAL_COST = 1.4142135623730951; //sqrt(2)

	private function __construct(){
	}

	/**
	 * Admissible octile distance for an 8-connected grid plus a unit-cost vertical term. Never overestimates the true
	 * minimum-cost path, which keeps A* optimal.
	 */
	public static function octile(int $dx, int $dy, int $dz) : float{
		$ax = abs($dx);
		$az = abs($dz);
		$horizontal = (self::DIAGONAL_COST * min($ax, $az)) + (self::ORTHOGONAL_COST * (max($ax, $az) - min($ax, $az)));
		return $horizontal + abs($dy);
	}

	public static function distance(int $x1, int $y1, int $z1, int $x2, int $y2, int $z2) : float{
		$dx = $x1 - $x2;
		$dy = $y1 - $y2;
		$dz = $z1 - $z2;
		return sqrt(($dx * $dx) + ($dy * $dy) + ($dz * $dz));
	}
}
