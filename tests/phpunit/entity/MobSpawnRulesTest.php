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

class MobSpawnRulesTest extends TestCase{

	public function testHostilesSpawnOnlyInDarkness() : void{
		self::assertTrue(MobSpawnRules::canHostileSpawnAt(0));
		self::assertTrue(MobSpawnRules::canHostileSpawnAt(MobSpawnRules::HOSTILE_MAX_LIGHT));
		self::assertFalse(MobSpawnRules::canHostileSpawnAt(MobSpawnRules::HOSTILE_MAX_LIGHT + 1));
		self::assertFalse(MobSpawnRules::canHostileSpawnAt(15));
	}

	public function testPassivesSpawnOnlyInLight() : void{
		self::assertTrue(MobSpawnRules::canPassiveSpawnAt(15));
		self::assertTrue(MobSpawnRules::canPassiveSpawnAt(MobSpawnRules::PASSIVE_MIN_LIGHT));
		self::assertFalse(MobSpawnRules::canPassiveSpawnAt(MobSpawnRules::PASSIVE_MIN_LIGHT - 1));
		self::assertFalse(MobSpawnRules::canPassiveSpawnAt(0));
	}

	public function testCap() : void{
		self::assertTrue(MobSpawnRules::isUnderCap(0, 12));
		self::assertTrue(MobSpawnRules::isUnderCap(11, 12));
		self::assertFalse(MobSpawnRules::isUnderCap(12, 12));
		self::assertFalse(MobSpawnRules::isUnderCap(20, 12));
	}

	public function testHostileCapIsTighterThanPassive() : void{
		//nights were flooding with monsters: hostiles are now capped below passive animals on purpose
		self::assertLessThan(MobSpawnRules::PASSIVE_CAP, MobSpawnRules::HOSTILE_CAP);
	}

	public function testPackSizeWithinBounds() : void{
		for($roll = 0; $roll < 50; ++$roll){
			$size = MobSpawnRules::packSize($roll, MobSpawnRules::MAX_HOSTILE_PACK);
			self::assertGreaterThanOrEqual(1, $size);
			self::assertLessThanOrEqual(MobSpawnRules::MAX_HOSTILE_PACK, $size);
		}
		self::assertSame(1, MobSpawnRules::packSize(123, 1));
	}
}
