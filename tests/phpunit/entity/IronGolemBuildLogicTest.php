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

use PHPUnit\Framework\TestCase;

class IronGolemBuildLogicTest extends TestCase{

	public function testCompleteFrameAlongX() : void{
		self::assertSame(IronGolemBuildLogic::AXIS_X, IronGolemBuildLogic::matchedAxis(true, true, true, true, false, false));
	}

	public function testCompleteFrameAlongZ() : void{
		self::assertSame(IronGolemBuildLogic::AXIS_Z, IronGolemBuildLogic::matchedAxis(true, true, false, false, true, true));
	}

	public function testMissingBodyNeverBuilds() : void{
		self::assertNull(IronGolemBuildLogic::matchedAxis(false, true, true, true, true, true));
	}

	public function testMissingBaseNeverBuilds() : void{
		self::assertNull(IronGolemBuildLogic::matchedAxis(true, false, true, true, true, true));
	}

	public function testOneArmIsNotEnough() : void{
		self::assertNull(IronGolemBuildLogic::matchedAxis(true, true, true, false, false, false));
		self::assertNull(IronGolemBuildLogic::matchedAxis(true, true, false, false, false, true));
	}

	public function testNoArmsIsNotEnough() : void{
		self::assertNull(IronGolemBuildLogic::matchedAxis(true, true, false, false, false, false));
	}

	public function testFullPlusShapePrefersX() : void{
		//all four arms present (a full plus): the X pair is consumed
		self::assertSame(IronGolemBuildLogic::AXIS_X, IronGolemBuildLogic::matchedAxis(true, true, true, true, true, true));
	}
}
