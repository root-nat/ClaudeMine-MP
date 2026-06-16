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

class MobDespawnRulesTest extends TestCase{

	public function testHostilesExtendMonsterSoTheyDespawn() : void{
		//Zombie historically extended AbstractMob directly and never despawned; lock all hostiles under Monster
		self::assertTrue(is_a(Zombie::class, Monster::class, true), "Zombies must extend Monster to despawn/burn");
		self::assertTrue(is_a(Skeleton::class, Monster::class, true));
		self::assertTrue(is_a(Creeper::class, Monster::class, true));
	}

	public function testNoPlayerAlwaysDespawns() : void{
		self::assertTrue(MobDespawnRules::shouldDespawn(null, 5));
	}

	public function testBeyondInstantDistanceAlwaysDespawns() : void{
		$distSq = (float) ((MobDespawnRules::INSTANT_DESPAWN_DISTANCE + 1) ** 2);
		self::assertTrue(MobDespawnRules::shouldDespawn($distSq, 5)); //roll irrelevant past 128
	}

	public function testRandomZoneDespawnsOnlyOnZeroRoll() : void{
		$distSq = (float) ((MobDespawnRules::RANDOM_DESPAWN_DISTANCE + 8) ** 2);
		self::assertTrue(MobDespawnRules::shouldDespawn($distSq, 0));
		self::assertFalse(MobDespawnRules::shouldDespawn($distSq, 1));
		self::assertFalse(MobDespawnRules::shouldDespawn($distSq, 39));
	}

	public function testWithinSafeDistanceNeverDespawns() : void{
		$distSq = (float) ((MobDespawnRules::RANDOM_DESPAWN_DISTANCE - 1) ** 2);
		self::assertFalse(MobDespawnRules::shouldDespawn($distSq, 0));
		self::assertFalse(MobDespawnRules::shouldDespawn(0.0, 0));
	}
}
