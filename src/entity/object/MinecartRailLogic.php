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

namespace pocketmine\entity\object;

use pocketmine\block\utils\RailConnectionInfo;
use pocketmine\math\Facing;
use function array_map;
use function count;
use function in_array;

/**
 * Pure-logic helper for minecart movement along rails. Kept separate from the entity so the direction maths can be
 * unit-tested without a live World.
 */
final class MinecartRailLogic{

	private function __construct(){
	}

	/**
	 * Returns the two horizontal facing directions a rail of the given shape connects to, or null if the shape is
	 * unknown. Ascent flags are stripped.
	 *
	 * @return int[]|null
	 * @phpstan-return array{int, int}|null
	 */
	public static function getConnectionsForShape(int $shape) : ?array{
		$raw = RailConnectionInfo::CONNECTIONS[$shape] ?? RailConnectionInfo::CURVE_CONNECTIONS[$shape] ?? null;
		if($raw === null){
			return null;
		}
		$horizontal = array_map(fn(int $c) => $c & ~RailConnectionInfo::FLAG_ASCEND, $raw);
		return [$horizontal[0], $horizontal[1]];
	}

	/**
	 * Returns whether the given rail shape ascends (slope), based on the presence of an ascend flag.
	 */
	public static function isAscending(int $shape) : bool{
		$raw = RailConnectionInfo::CONNECTIONS[$shape] ?? RailConnectionInfo::CURVE_CONNECTIONS[$shape] ?? null;
		if($raw === null){
			return false;
		}
		foreach($raw as $c){
			if(($c & RailConnectionInfo::FLAG_ASCEND) !== 0){
				return true;
			}
		}
		return false;
	}

	/**
	 * Given the rail's connection directions and the cart's current horizontal travel direction, returns the direction
	 * the cart should continue travelling in. The cart leaves a rail via the connection it did not enter through; for
	 * straight rails this preserves the travel direction, while for curves it turns the cart onto the new axis.
	 *
	 * @param int[] $connections
	 * @phpstan-param array{int, int} $connections
	 */
	public static function getNextDirection(array $connections, int $currentFacing) : int{
		[$a, $b] = $connections;

		$entryFrom = Facing::opposite($currentFacing);
		if($entryFrom === $a){
			return $b;
		}
		if($entryFrom === $b){
			return $a;
		}

		//cart isn't aligned with the rail axis (e.g. just placed, or moved onto a perpendicular curve) - prefer keeping
		//the current direction if the rail supports it, otherwise snap to the first available connection
		if(in_array($currentFacing, $connections, true)){
			return $currentFacing;
		}
		return $a;
	}

	/**
	 * Returns true if a cart travelling in the given direction can actually move along a rail with these connections
	 * (i.e. the direction or its opposite is one of the rail's connections).
	 *
	 * @param int[] $connections
	 * @phpstan-param array{int, int} $connections
	 */
	public static function canTravel(array $connections, int $facing) : bool{
		return count($connections) === 2 && (in_array($facing, $connections, true) || in_array(Facing::opposite($facing), $connections, true));
	}
}
