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

namespace pocketmine\world;

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\types\DimensionIds;

class DimensionTest extends TestCase{

	public function testFromGeneratorName() : void{
		self::assertSame(Dimension::NETHER, Dimension::fromGeneratorName("nether"));
		self::assertSame(Dimension::NETHER, Dimension::fromGeneratorName("HELL"));
		self::assertSame(Dimension::THE_END, Dimension::fromGeneratorName("end"));
		self::assertSame(Dimension::THE_END, Dimension::fromGeneratorName("the_end"));
		self::assertSame(Dimension::OVERWORLD, Dimension::fromGeneratorName("normal"));
		self::assertSame(Dimension::OVERWORLD, Dimension::fromGeneratorName("flat"));
		self::assertSame(Dimension::OVERWORLD, Dimension::fromGeneratorName("somecustomgenerator"));
	}

	public function testSaveNameRoundTrip() : void{
		foreach(Dimension::cases() as $dimension){
			self::assertSame($dimension, Dimension::fromSaveName($dimension->getSaveName()));
		}
		self::assertNull(Dimension::fromSaveName("unknown"));
	}

	public function testNetworkIds() : void{
		self::assertSame(DimensionIds::OVERWORLD, Dimension::OVERWORLD->getNetworkId());
		self::assertSame(DimensionIds::NETHER, Dimension::NETHER->getNetworkId());
		self::assertSame(DimensionIds::THE_END, Dimension::THE_END->getNetworkId());
	}

	public function testCoordinateScale() : void{
		self::assertSame(8.0, Dimension::NETHER->getCoordinateScale());
		self::assertSame(1.0, Dimension::OVERWORLD->getCoordinateScale());
		self::assertSame(1.0, Dimension::THE_END->getCoordinateScale());
	}

	public function testEnvironmentFlags() : void{
		self::assertTrue(Dimension::OVERWORLD->hasSkyLight());
		self::assertTrue(Dimension::OVERWORLD->hasWeather());
		self::assertTrue(Dimension::OVERWORLD->hasDayNightCycle());
		foreach([Dimension::NETHER, Dimension::THE_END] as $dimension){
			self::assertFalse($dimension->hasSkyLight());
			self::assertFalse($dimension->hasWeather());
			self::assertFalse($dimension->hasDayNightCycle());
		}
	}
}
