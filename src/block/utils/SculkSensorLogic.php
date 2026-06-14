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
 * Pure phase state machine for a sculk sensor, isolated from the live world for unit-testing. A sensor scans for a nearby
 * vibration (a moving entity); on detection it powers on for a short ACTIVE window, then a brief COOLDOWN where it ignores
 * everything, before returning to scanning. {@link \pocketmine\block\SculkSensor} feeds the live vibration result here.
 */
final class SculkSensorLogic{

	public const PHASE_INACTIVE = 0;
	public const PHASE_ACTIVE = 1;
	public const PHASE_COOLDOWN = 2;

	/** Ticks the sensor stays powered after a vibration. */
	public const ACTIVE_TICKS = 40;
	/** Ticks the sensor ignores vibrations before it can re-trigger. */
	public const COOLDOWN_TICKS = 10;
	/** Ticks between scans while idle. */
	public const SCAN_INTERVAL = 4;
	/** Radius (blocks) a vibration is detected within. */
	public const VIBRATION_RANGE = 8;

	private function __construct(){
		//NOOP
	}

	/**
	 * Computes the next phase from the current one and whether a vibration is present this scan.
	 *
	 * @return array{int, bool, int} [nextPhase, powered, delayTicks until the next scheduled update]
	 */
	public static function nextState(int $phase, bool $vibrationDetected) : array{
		return match($phase){
			self::PHASE_ACTIVE => [self::PHASE_COOLDOWN, false, self::COOLDOWN_TICKS],
			self::PHASE_COOLDOWN => [self::PHASE_INACTIVE, false, self::SCAN_INTERVAL],
			default => $vibrationDetected
				? [self::PHASE_ACTIVE, true, self::ACTIVE_TICKS]
				: [self::PHASE_INACTIVE, false, self::SCAN_INTERVAL],
		};
	}
}
