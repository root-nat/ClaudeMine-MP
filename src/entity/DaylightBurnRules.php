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

namespace pocketmine\entity;

/**
 * Pure rules for undead (zombie/skeleton) burning in daylight, isolated from the live world for unit-testing. A mob burns
 * only when the sun is up, it has open sky above it, it isn't in water and it isn't raining - which makes night-spawned
 * monsters catch fire each morning instead of piling up day after day. {@link Monster} resolves the live inputs.
 */
final class DaylightBurnRules{

	/** Time-of-day (0-23999) is "bright day" below this... */
	public const DAY_END = 12000;
	/** ...or at/after this (sunrise), so monsters already start burning at dawn. */
	public const DAWN_START = 23000;

	private function __construct(){
		//NOOP
	}

	public static function isDaytime(int $timeOfDay) : bool{
		return $timeOfDay < self::DAY_END || $timeOfDay >= self::DAWN_START;
	}

	public static function shouldBurn(int $timeOfDay, bool $skyExposed, bool $inWater, bool $raining) : bool{
		if(!$skyExposed || $inWater || $raining){
			return false;
		}
		return self::isDaytime($timeOfDay);
	}
}
