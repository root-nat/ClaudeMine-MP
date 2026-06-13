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

namespace pocketmine\entity\boss;

/**
 * Detects the wither spawn structure - a horizontal row of three soul-sand "arms" with a soul-sand stem below the
 * centre, capped by three wither-skeleton skulls - when the final skull is placed. Pure geometry over injected
 * block predicates, so it is unit-testable without a live world; the two horizontal orientations (along X and Z) are
 * both checked.
 */
final class WitherSpawnPattern{

	private function __construct(){
	}

	/**
	 * Given the position where a wither skull was just placed and predicates identifying skull/soul-sand blocks, returns
	 * the matched structure as [centreX, centreY, centreZ, alongX] - where alongX indicates the arm axis - or null if no
	 * valid structure touches that position. Returning the axis makes it the single source of truth so callers never
	 * re-derive it from a possibly-changed world.
	 *
	 * @phpstan-param \Closure(int, int, int) : bool $isSkull
	 * @phpstan-param \Closure(int, int, int) : bool $isSoulSand
	 *
	 * @return array{int, int, int, bool}|null
	 */
	public static function findCentre(\Closure $isSkull, \Closure $isSoulSand, int $placedX, int $placedY, int $placedZ) : ?array{
		foreach([[1, 0], [0, 1]] as [$dx, $dz]){
			//the placed skull may be the left arm, centre, or right arm
			for($offset = -1; $offset <= 1; ++$offset){
				$cx = $placedX - $offset * $dx;
				$cz = $placedZ - $offset * $dz;
				if(self::matches($isSkull, $isSoulSand, $cx, $placedY, $cz, $dx, $dz)){
					return [$cx, $placedY, $cz, $dx === 1];
				}
			}
		}
		return null;
	}

	/**
	 * @phpstan-param \Closure(int, int, int) : bool $isSkull
	 * @phpstan-param \Closure(int, int, int) : bool $isSoulSand
	 */
	private static function matches(\Closure $isSkull, \Closure $isSoulSand, int $cx, int $cy, int $cz, int $dx, int $dz) : bool{
		//three skulls along the axis at head level
		for($k = -1; $k <= 1; ++$k){
			if(!$isSkull($cx + $k * $dx, $cy, $cz + $k * $dz)){
				return false;
			}
		}
		//three soul-sand arms directly below the skulls
		for($k = -1; $k <= 1; ++$k){
			if(!$isSoulSand($cx + $k * $dx, $cy - 1, $cz + $k * $dz)){
				return false;
			}
		}
		//one soul-sand stem below the centre arm
		return $isSoulSand($cx, $cy - 2, $cz);
	}

	/**
	 * Returns every block position that makes up the wither structure for the given centre and axis, used to clear the
	 * structure when the wither spawns.
	 *
	 * @phpstan-param array{int, int, int} $centre
	 *
	 * @return int[][]
	 * @phpstan-return list<array{int, int, int}>
	 */
	public static function structureBlocks(array $centre, bool $alongX) : array{
		[$cx, $cy, $cz] = $centre;
		$dx = $alongX ? 1 : 0;
		$dz = $alongX ? 0 : 1;
		$blocks = [];
		for($k = -1; $k <= 1; ++$k){
			$blocks[] = [$cx + $k * $dx, $cy, $cz + $k * $dz];     //skulls
			$blocks[] = [$cx + $k * $dx, $cy - 1, $cz + $k * $dz]; //arms
		}
		$blocks[] = [$cx, $cy - 2, $cz]; //stem
		return $blocks;
	}
}
