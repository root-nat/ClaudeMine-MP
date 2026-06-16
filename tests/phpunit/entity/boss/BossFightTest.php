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

namespace pocketmine\entity\boss;

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\types\BossBarColor;

class BossFightTest extends TestCase{

	public function testBossBarClampsPercentage() : void{
		$bar = new BossBar("Wither", BossBarColor::PURPLE);
		$bar->setPercentage(1.5);
		self::assertSame(1.0, $bar->getPercentage());
		$bar->setPercentage(-0.5);
		self::assertSame(0.0, $bar->getPercentage());
		$bar->setPercentage(0.42);
		self::assertSame(0.42, $bar->getPercentage());
	}

	public function testBossBarHealthRatio() : void{
		$bar = new BossBar("Ender Dragon");
		$bar->setHealth(150, 200);
		self::assertSame(0.75, $bar->getPercentage());
		$bar->setHealth(10, 0); //avoid division by zero
		self::assertSame(0.0, $bar->getPercentage());
	}

	public function testDragonInvulnerableWhileCrystalsAlive() : void{
		$state = new DragonFightState(10);
		self::assertTrue($state->isHealing());
		//body hit is fully negated while crystals heal it
		self::assertSame(0.0, $state->resolveDamage(20.0, false));
	}

	public function testDragonVulnerableWhenAllCrystalsDestroyed() : void{
		$state = new DragonFightState(2);
		$state->destroyCrystal();
		$state->destroyCrystal();
		self::assertSame(0, $state->getAliveCrystals());
		self::assertFalse($state->isHealing());
		self::assertSame(20.0, $state->resolveDamage(20.0, false));
	}

	public function testPerchHitsAlwaysLandEvenWithCrystals() : void{
		$state = new DragonFightState(10);
		self::assertSame(15.0, $state->resolveDamage(15.0, true));
	}

	public function testPerchedPhaseIsVulnerable() : void{
		$state = new DragonFightState(10);
		$state->setPhase(DragonPhase::PERCHED);
		self::assertTrue($state->getPhase()->isPerched());
		self::assertSame(12.0, $state->resolveDamage(12.0, false));
	}

	public function testHealScalesWithCrystals() : void{
		$state = new DragonFightState(10);
		self::assertSame(10.0, $state->healPerCycle());
		$state->destroyCrystal();
		self::assertSame(9.0, $state->healPerCycle());
	}

	public function testCrystalCountNeverNegative() : void{
		$state = new DragonFightState(1);
		$state->destroyCrystal();
		$state->destroyCrystal();
		self::assertSame(0, $state->getAliveCrystals());
	}

	public function testRespawnRequiresFourCrystals() : void{
		self::assertFalse(DragonFightState::canRespawn(3));
		self::assertTrue(DragonFightState::canRespawn(4));
		self::assertTrue(DragonFightState::canRespawn(10));
	}
}
