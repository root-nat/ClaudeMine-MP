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

class CreeperSwellLogicTest extends TestCase{

	public function testFuseRisesWhenTargetInRange() : void{
		$fuse = CreeperSwellLogic::updateFuse(0, 2.0);
		self::assertSame(1, $fuse);
	}

	public function testFuseFallsWhenTargetFar() : void{
		$fuse = CreeperSwellLogic::updateFuse(5, 6.0);
		self::assertSame(4, $fuse);
	}

	public function testFuseFallsWithNoTarget() : void{
		self::assertSame(2, CreeperSwellLogic::updateFuse(3, null));
	}

	public function testFuseClampedToZero() : void{
		self::assertSame(0, CreeperSwellLogic::updateFuse(0, 10.0));
	}

	public function testFuseClampedToMax() : void{
		self::assertSame(CreeperSwellLogic::MAX_FUSE, CreeperSwellLogic::updateFuse(CreeperSwellLogic::MAX_FUSE, 1.0));
	}

	public function testExplodesAtMaxFuse() : void{
		self::assertFalse(CreeperSwellLogic::shouldExplode(CreeperSwellLogic::MAX_FUSE - 1));
		self::assertTrue(CreeperSwellLogic::shouldExplode(CreeperSwellLogic::MAX_FUSE));
	}

	public function testReachesExplosionByHoldingTargetClose() : void{
		$fuse = 0;
		for($i = 0; $i < CreeperSwellLogic::MAX_FUSE; ++$i){
			$fuse = CreeperSwellLogic::updateFuse($fuse, 1.0);
		}
		self::assertTrue(CreeperSwellLogic::shouldExplode($fuse));
	}

	public function testSwellDirection() : void{
		self::assertSame(1, CreeperSwellLogic::swellDirection(0, 1));
		self::assertSame(-1, CreeperSwellLogic::swellDirection(5, 4));
		self::assertSame(0, CreeperSwellLogic::swellDirection(3, 3));
	}
}
