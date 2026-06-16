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

class DaylightBurnRulesTest extends TestCase{

	public function testBurnsInOpenMiddayLight() : void{
		self::assertTrue(DaylightBurnRules::shouldBurn(6000, true, false, false));
	}

	public function testBurnsAtDawnButNotAtNight() : void{
		self::assertTrue(DaylightBurnRules::isDaytime(23500), "Sunrise should burn the undead");
		self::assertTrue(DaylightBurnRules::isDaytime(0));
		self::assertFalse(DaylightBurnRules::isDaytime(15000), "Deep night is safe");
		self::assertFalse(DaylightBurnRules::shouldBurn(15000, true, false, false));
	}

	public function testShadeStopsBurning() : void{
		self::assertFalse(DaylightBurnRules::shouldBurn(6000, false, false, false), "A block overhead shades the mob");
	}

	public function testWaterAndRainStopBurning() : void{
		self::assertFalse(DaylightBurnRules::shouldBurn(6000, true, true, false), "Standing in water never burns");
		self::assertFalse(DaylightBurnRules::shouldBurn(6000, true, false, true), "Rain puts the fire out");
	}
}
