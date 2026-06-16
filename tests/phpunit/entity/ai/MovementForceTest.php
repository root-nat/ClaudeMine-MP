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

class MovementForceTest extends TestCase{

	public function testForceIsPositiveAndScalesWithSpeed() : void{
		$slow = MovementForce::computeForce(0.1);
		$fast = MovementForce::computeForce(0.3);
		self::assertGreaterThan(0.0, $slow);
		self::assertGreaterThan($slow, $fast);
	}

	public function testForceReachesTargetSteadyStateSpeed() : void{
		$friction = 0.546;
		$targetSpeed = 0.25;
		$force = MovementForce::computeForce($targetSpeed, $friction);

		//simulate the engine recurrence v_{n+1} = (v_n + force) * friction
		$v = 0.0;
		for($i = 0; $i < 200; ++$i){
			$v = ($v + $force) * $friction;
		}
		self::assertEqualsWithDelta($targetSpeed, $v, 0.0001);
	}

	public function testSteadyStateInverseOfComputeForce() : void{
		$force = 0.2;
		$friction = 0.6;
		$speed = MovementForce::steadyStateSpeed($force, $friction);
		self::assertEqualsWithDelta($force, MovementForce::computeForce($speed, $friction), 0.0001);
	}

	public function testDegenerateFrictionReturnsSpeed() : void{
		self::assertSame(0.25, MovementForce::computeForce(0.25, 0.0));
		self::assertSame(0.25, MovementForce::computeForce(0.25, 1.0));
	}

	public function testAccelerationForceAddsNothingWhenAtOrAboveTarget() : void{
		$target = 0.2;
		self::assertSame(0.0, MovementForce::accelerationForce($target, $target));       //already cruising
		self::assertSame(0.0, MovementForce::accelerationForce($target, 0.5));           //flung by knockback
	}

	public function testAccelerationForceAcceleratesWhenBelowTarget() : void{
		$target = 0.2;
		self::assertSame(MovementForce::computeForce($target), MovementForce::accelerationForce($target, 0.0));
		self::assertSame(MovementForce::computeForce($target), MovementForce::accelerationForce($target, 0.1));
	}

	public function testKnockbackDecaysInsteadOfBeingSustained() : void{
		$friction = MovementForce::DEFAULT_GROUND_FRICTION;
		$target = 0.2;
		$kb = 0.5;

		//with the velocity-aware force, a fleeing mob must not sustain the knockback velocity
		$capped = $kb;
		for($i = 0; $i < 4; ++$i){
			$capped = ($capped + MovementForce::accelerationForce($target, $capped, $friction)) * $friction;
		}

		//the old unconditional force kept pushing in the same direction, sustaining a higher speed
		$uncapped = $kb;
		$force = MovementForce::computeForce($target, $friction);
		for($i = 0; $i < 4; ++$i){
			$uncapped = ($uncapped + $force) * $friction;
		}

		self::assertLessThan($uncapped, $capped, "Velocity-aware force must let knockback decay faster");
		self::assertLessThanOrEqual($target + 1e-9, $capped, "Fleeing mob should not exceed its walk speed");
	}
}
