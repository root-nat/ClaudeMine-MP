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

class SlimeSizeLogicTest extends TestCase{

	public function testHealthIsSizeSquared() : void{
		self::assertSame(1, SlimeSizeLogic::healthFor(SlimeSizeLogic::SIZE_SMALL));
		self::assertSame(4, SlimeSizeLogic::healthFor(SlimeSizeLogic::SIZE_MEDIUM));
		self::assertSame(16, SlimeSizeLogic::healthFor(SlimeSizeLogic::SIZE_LARGE));
	}

	public function testSlimeDamage() : void{
		self::assertSame(0, SlimeSizeLogic::slimeDamage(SlimeSizeLogic::SIZE_SMALL)); //tiny slimes are harmless
		self::assertSame(2, SlimeSizeLogic::slimeDamage(SlimeSizeLogic::SIZE_MEDIUM));
		self::assertSame(4, SlimeSizeLogic::slimeDamage(SlimeSizeLogic::SIZE_LARGE));
	}

	public function testMagmaCubeHitsHarder() : void{
		self::assertSame(0, SlimeSizeLogic::magmaCubeDamage(SlimeSizeLogic::SIZE_SMALL));
		self::assertGreaterThan(SlimeSizeLogic::slimeDamage(SlimeSizeLogic::SIZE_LARGE), SlimeSizeLogic::magmaCubeDamage(SlimeSizeLogic::SIZE_LARGE));
	}

	public function testXpEqualsSize() : void{
		self::assertSame(2, SlimeSizeLogic::xpFor(SlimeSizeLogic::SIZE_MEDIUM));
	}

	public function testHitboxScalesWithSize() : void{
		self::assertEqualsWithDelta(0.51, SlimeSizeLogic::hitboxEdge(1), 0.0001);
		self::assertEqualsWithDelta(2.04, SlimeSizeLogic::hitboxEdge(4), 0.0001);
	}

	public function testOnlyLargerSlimesSplit() : void{
		self::assertFalse(SlimeSizeLogic::canSplit(SlimeSizeLogic::SIZE_SMALL));
		self::assertTrue(SlimeSizeLogic::canSplit(SlimeSizeLogic::SIZE_MEDIUM));
		self::assertTrue(SlimeSizeLogic::canSplit(SlimeSizeLogic::SIZE_LARGE));
	}

	public function testChildSizeHalves() : void{
		self::assertSame(SlimeSizeLogic::SIZE_MEDIUM, SlimeSizeLogic::childSize(SlimeSizeLogic::SIZE_LARGE));
		self::assertSame(SlimeSizeLogic::SIZE_SMALL, SlimeSizeLogic::childSize(SlimeSizeLogic::SIZE_MEDIUM));
		self::assertSame(SlimeSizeLogic::SIZE_SMALL, SlimeSizeLogic::childSize(SlimeSizeLogic::SIZE_SMALL));
	}

	public function testSplitCountInVanillaRange() : void{
		for($roll = 0; $roll < 30; ++$roll){
			$count = SlimeSizeLogic::splitCount($roll);
			self::assertGreaterThanOrEqual(2, $count);
			self::assertLessThanOrEqual(4, $count);
		}
	}

	public function testHopParamsScaleWithSize() : void{
		//bigger slimes leap farther...
		self::assertGreaterThan(
			SlimeSizeLogic::hopHorizontalSpeed(SlimeSizeLogic::SIZE_SMALL),
			SlimeSizeLogic::hopHorizontalSpeed(SlimeSizeLogic::SIZE_LARGE)
		);
		//...but a touch less often
		self::assertGreaterThan(
			SlimeSizeLogic::hopIntervalTicks(SlimeSizeLogic::SIZE_SMALL),
			SlimeSizeLogic::hopIntervalTicks(SlimeSizeLogic::SIZE_LARGE)
		);
		self::assertGreaterThan(0.0, SlimeSizeLogic::hopHorizontalSpeed(SlimeSizeLogic::SIZE_MEDIUM));
		//out-of-range sizes are clamped, never producing degenerate (zero/negative) hops
		self::assertGreaterThan(0, SlimeSizeLogic::hopIntervalTicks(99));
		self::assertGreaterThan(0.0, SlimeSizeLogic::hopHorizontalSpeed(0));
	}

	public function testClampSizeSnapsToValidSizes() : void{
		self::assertSame(SlimeSizeLogic::SIZE_SMALL, SlimeSizeLogic::clampSize(0));
		self::assertSame(SlimeSizeLogic::SIZE_SMALL, SlimeSizeLogic::clampSize(1));
		self::assertSame(SlimeSizeLogic::SIZE_MEDIUM, SlimeSizeLogic::clampSize(3));
		self::assertSame(SlimeSizeLogic::SIZE_LARGE, SlimeSizeLogic::clampSize(99));
	}
}
