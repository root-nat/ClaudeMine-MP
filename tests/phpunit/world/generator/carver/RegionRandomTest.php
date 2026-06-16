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

namespace pocketmine\world\generator\carver;

use PHPUnit\Framework\TestCase;

class RegionRandomTest extends TestCase{

	public function testSameTupleSameSeed() : void{
		self::assertSame(
			RegionRandom::seedFor(12345, 3, -7),
			RegionRandom::seedFor(12345, 3, -7)
		);
	}

	public function testDifferentRegionDifferentSeed() : void{
		self::assertNotSame(
			RegionRandom::seedFor(12345, 3, -7),
			RegionRandom::seedFor(12345, 3, -6)
		);
		self::assertNotSame(
			RegionRandom::seedFor(12345, 3, -7),
			RegionRandom::seedFor(12345, 4, -7)
		);
	}

	public function testDifferentSaltDifferentSeed() : void{
		self::assertNotSame(
			RegionRandom::seedFor(1, 0, 0, 1),
			RegionRandom::seedFor(1, 0, 0, 2)
		);
	}

	public function testDifferentWorldSeedDifferentSeed() : void{
		self::assertNotSame(
			RegionRandom::seedFor(1, 0, 0),
			RegionRandom::seedFor(2, 0, 0)
		);
	}

	public function testDeriveProducesDeterministicSequence() : void{
		$a = RegionRandom::derive(999, 1, 2);
		$b = RegionRandom::derive(999, 1, 2);
		for($i = 0; $i < 20; ++$i){
			self::assertSame($a->nextBoundedInt(1000), $b->nextBoundedInt(1000));
		}
	}

	public function testSeedIsNonNegative() : void{
		self::assertGreaterThanOrEqual(0, RegionRandom::seedFor(-999999, -50000, 88888));
	}
}
