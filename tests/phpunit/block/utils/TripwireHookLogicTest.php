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

class TripwireHookLogicTest extends TestCase{

	public function testTwoAdjacentHooksConnectWithNoString() : void{
		[$connected, $distance, $powered] = TripwireHookLogic::scan([TripwireHookLogic::CELL_MATCHING_HOOK]);
		self::assertTrue($connected);
		self::assertSame(1, $distance);
		self::assertFalse($powered);
	}

	public function testConnectsThroughStringToFarHook() : void{
		[$connected, $distance, $powered] = TripwireHookLogic::scan([
			TripwireHookLogic::CELL_WIRE,
			TripwireHookLogic::CELL_WIRE,
			TripwireHookLogic::CELL_MATCHING_HOOK,
		]);
		self::assertTrue($connected);
		self::assertSame(3, $distance);
		self::assertFalse($powered, "No entity on the wire yet");
	}

	public function testPoweredWhenAnyStringTriggered() : void{
		[$connected, $distance, $powered] = TripwireHookLogic::scan([
			TripwireHookLogic::CELL_WIRE,
			TripwireHookLogic::CELL_WIRE_TRIGGERED,
			TripwireHookLogic::CELL_WIRE,
			TripwireHookLogic::CELL_MATCHING_HOOK,
		]);
		self::assertTrue($connected);
		self::assertSame(4, $distance);
		self::assertTrue($powered);
	}

	public function testNotConnectedWithoutFarHook() : void{
		[$connected, , $powered] = TripwireHookLogic::scan([
			TripwireHookLogic::CELL_WIRE,
			TripwireHookLogic::CELL_WIRE_TRIGGERED, //a triggered wire alone must NOT power a dangling hook
		]);
		self::assertFalse($connected);
		self::assertFalse($powered);
	}

	public function testBrokenLineDoesNotConnect() : void{
		[$connected] = TripwireHookLogic::scan([
			TripwireHookLogic::CELL_WIRE,
			TripwireHookLogic::CELL_BLOCKED, //gap breaks the circuit even if a hook sits beyond it
			TripwireHookLogic::CELL_MATCHING_HOOK,
		]);
		self::assertFalse($connected);
	}

	public function testWrongFacingHookBlocks() : void{
		//a hook not facing back is reported as CELL_BLOCKED by the caller, so the line just ends unconnected
		[$connected] = TripwireHookLogic::scan([
			TripwireHookLogic::CELL_WIRE,
			TripwireHookLogic::CELL_BLOCKED,
		]);
		self::assertFalse($connected);
	}
}
