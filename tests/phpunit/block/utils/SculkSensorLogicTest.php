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

use PHPUnit\Framework\TestCase;

class SculkSensorLogicTest extends TestCase{

	public function testIdleScansUntilAVibration() : void{
		[$phase, $powered, $delay] = SculkSensorLogic::nextState(SculkSensorLogic::PHASE_INACTIVE, false);
		self::assertSame(SculkSensorLogic::PHASE_INACTIVE, $phase);
		self::assertFalse($powered);
		self::assertSame(SculkSensorLogic::SCAN_INTERVAL, $delay);
	}

	public function testVibrationPowersItOn() : void{
		[$phase, $powered, $delay] = SculkSensorLogic::nextState(SculkSensorLogic::PHASE_INACTIVE, true);
		self::assertSame(SculkSensorLogic::PHASE_ACTIVE, $phase);
		self::assertTrue($powered);
		self::assertSame(SculkSensorLogic::ACTIVE_TICKS, $delay);
	}

	public function testActiveAlwaysFallsToCooldown() : void{
		//while active it ignores vibrations and always winds down to cooldown next
		foreach([true, false] as $vibration){
			[$phase, $powered, $delay] = SculkSensorLogic::nextState(SculkSensorLogic::PHASE_ACTIVE, $vibration);
			self::assertSame(SculkSensorLogic::PHASE_COOLDOWN, $phase);
			self::assertFalse($powered);
			self::assertSame(SculkSensorLogic::COOLDOWN_TICKS, $delay);
		}
	}

	public function testCooldownReturnsToIdle() : void{
		[$phase, $powered, $delay] = SculkSensorLogic::nextState(SculkSensorLogic::PHASE_COOLDOWN, true);
		self::assertSame(SculkSensorLogic::PHASE_INACTIVE, $phase);
		self::assertFalse($powered);
		self::assertSame(SculkSensorLogic::SCAN_INTERVAL, $delay);
	}
}
