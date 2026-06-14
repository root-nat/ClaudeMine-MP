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

/**
 * Pure tripwire-hook circuit logic, isolated from the live world for unit-testing. A hook scans the line of blocks in the
 * direction it faces: a continuous run of tripwire string ending in an opposing hook forms a circuit. The circuit is
 * powered when any string in the run has an entity on it. {@link \pocketmine\block\TripwireHook} resolves the live blocks
 * into the cell codes and applies the result.
 */
final class TripwireHookLogic{

	/** Anything that breaks the line (air, solid block, a hook facing the wrong way...). */
	public const CELL_BLOCKED = 0;
	/** A tripwire string with no entity on it. */
	public const CELL_WIRE = 1;
	/** A tripwire string with an entity on it (would power the circuit). */
	public const CELL_WIRE_TRIGGERED = 2;
	/** A hook facing back toward the scanning hook - the far end of the circuit. */
	public const CELL_MATCHING_HOOK = 3;

	/** Maximum blocks a hook scans for its partner (up to 40 string + the opposing hook). */
	public const MAX_DISTANCE = 41;

	private function __construct(){
		//NOOP
	}

	/**
	 * @param int[] $cells cell codes ordered from distance 1 outward from the hook
	 * @phpstan-param list<int> $cells
	 *
	 * @return array{bool, int, bool} [connected, distance to the far hook, powered]
	 */
	public static function scan(array $cells) : array{
		$powered = false;
		$distance = 0;
		foreach($cells as $i => $code){
			if($code === self::CELL_MATCHING_HOOK){
				return [true, $i + 1, $powered];
			}
			if($code === self::CELL_WIRE || $code === self::CELL_WIRE_TRIGGERED){
				if($code === self::CELL_WIRE_TRIGGERED){
					$powered = true;
				}
				continue;
			}
			break; //blocked: the line is broken, no circuit
		}
		return [false, $distance, false];
	}
}
