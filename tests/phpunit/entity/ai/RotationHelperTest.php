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

namespace pocketmine\entity\ai;

use PHPUnit\Framework\TestCase;

class RotationHelperTest extends TestCase{

	public function testNormalizeYaw() : void{
		self::assertEqualsWithDelta(350.0, RotationHelper::normalizeYaw(-10.0), 1e-6);
		self::assertEqualsWithDelta(10.0, RotationHelper::normalizeYaw(370.0), 1e-6);
		self::assertEqualsWithDelta(0.0, RotationHelper::normalizeYaw(720.0), 1e-6);
		self::assertEqualsWithDelta(123.0, RotationHelper::normalizeYaw(123.0), 1e-6);
	}

	public function testDirectionToYawMatchesCardinals() : void{
		self::assertEqualsWithDelta(0.0, RotationHelper::directionToYaw(0.0, 1.0), 1e-6);   //+Z = south
		self::assertEqualsWithDelta(90.0, RotationHelper::directionToYaw(-1.0, 0.0), 1e-6); //-X = west
		self::assertEqualsWithDelta(180.0, RotationHelper::directionToYaw(0.0, -1.0), 1e-6);//-Z = north
		self::assertEqualsWithDelta(270.0, RotationHelper::directionToYaw(1.0, 0.0), 1e-6); //+X = east
	}

	public function testAngleDifferenceTakesShortestArc() : void{
		self::assertEqualsWithDelta(20.0, RotationHelper::angleDifference(350.0, 10.0), 1e-6); //forward across 360
		self::assertEqualsWithDelta(-20.0, RotationHelper::angleDifference(10.0, 350.0), 1e-6);
		self::assertEqualsWithDelta(180.0, RotationHelper::angleDifference(0.0, 180.0), 1e-6);
	}

	public function testTurnTowardsSnapsWhenWithinStep() : void{
		self::assertEqualsWithDelta(90.0, RotationHelper::turnTowards(70.0, 90.0, 30.0), 1e-6);
		self::assertEqualsWithDelta(350.0, RotationHelper::turnTowards(0.0, 350.0, 30.0), 1e-6); //short way backwards
		self::assertEqualsWithDelta(10.0, RotationHelper::turnTowards(350.0, 10.0, 30.0), 1e-6); //short way across 360
	}

	public function testTurnTowardsIsCappedByStep() : void{
		self::assertEqualsWithDelta(30.0, RotationHelper::turnTowards(0.0, 90.0, 30.0), 1e-6);
		self::assertEqualsWithDelta(330.0, RotationHelper::turnTowards(0.0, 200.0, 30.0), 1e-6); //turns the short way (down)
	}

	public function testTurnTowardsReachesTargetInBoundedSteps() : void{
		$yaw = 0.0;
		$target = 175.0;
		for($i = 0; $i < 10; ++$i){
			$yaw = RotationHelper::turnTowards($yaw, $target, 30.0);
		}
		self::assertEqualsWithDelta($target, $yaw, 1e-6); //6 capped steps + snap, well within 10
	}
}
