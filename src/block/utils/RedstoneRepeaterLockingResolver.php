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

namespace pocketmine\block\utils;

use pocketmine\math\Axis;
use pocketmine\math\Facing;

/**
 * Pure logic for vanilla repeater "locking": a repeater is locked (its output frozen) while a powered repeater or
 * comparator on either side perpendicular to it points its output INTO the repeater. {@link \pocketmine\block\RedstoneRepeater}
 * resolves the live neighbour blocks into the side descriptors and applies this.
 */
final class RedstoneRepeaterLockingResolver{

	private function __construct(){
		//NOOP
	}

	/**
	 * The two horizontal facings perpendicular to a repeater facing along $facing's axis.
	 *
	 * @return int[]
	 * @phpstan-return array{int, int}
	 */
	public static function perpendicularSides(int $facing) : array{
		return Facing::axis($facing) === Axis::X ? [Facing::NORTH, Facing::SOUTH] : [Facing::EAST, Facing::WEST];
	}

	/**
	 * A neighbour on side $side (the direction from the repeater to that neighbour) locks the repeater when it is a
	 * powered repeater/comparator whose facing equals $side — i.e. its output points back at the repeater.
	 */
	public static function neighbourLocks(int $side, bool $neighbourPowered, ?int $neighbourFacing) : bool{
		return $neighbourPowered && $neighbourFacing === $side;
	}

	/**
	 * @param array<int, array{powered: bool, facing: int}|null> $sideSources block on each perpendicular side keyed by
	 *        side facing (a powered-source descriptor, or null when the side block isn't a repeater/comparator)
	 */
	public static function isLocked(int $repeaterFacing, array $sideSources) : bool{
		foreach(self::perpendicularSides($repeaterFacing) as $side){
			$source = $sideSources[$side] ?? null;
			if($source !== null && self::neighbourLocks($side, $source['powered'], $source['facing'])){
				return true;
			}
		}
		return false;
	}
}
