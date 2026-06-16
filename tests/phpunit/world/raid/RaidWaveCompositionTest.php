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

namespace pocketmine\world\raid;

use PHPUnit\Framework\TestCase;
use pocketmine\world\World;

class RaidWaveCompositionTest extends TestCase{

	public function testWaveCountByDifficulty() : void{
		self::assertSame(0, RaidWaveComposition::waveCount(World::DIFFICULTY_PEACEFUL));
		self::assertSame(3, RaidWaveComposition::waveCount(World::DIFFICULTY_EASY));
		self::assertSame(5, RaidWaveComposition::waveCount(World::DIFFICULTY_NORMAL));
		self::assertSame(7, RaidWaveComposition::waveCount(World::DIFFICULTY_HARD));
	}

	public function testFirstWaveIsPillagersOnly() : void{
		$comp = RaidWaveComposition::composition(1, World::DIFFICULTY_NORMAL, 1);
		self::assertSame(["PILLAGER" => 4], $comp);
	}

	public function testRavagerFirstAppearsWave3() : void{
		self::assertSame(0, RaidWaveComposition::baseCount(RaiderType::RAVAGER, 2));
		self::assertSame(1, RaidWaveComposition::baseCount(RaiderType::RAVAGER, 3));
	}

	public function testEvokerAndWitchAppearMidRaid() : void{
		self::assertSame(1, RaidWaveComposition::baseCount(RaiderType::EVOKER, 5));
		self::assertSame(3, RaidWaveComposition::baseCount(RaiderType::WITCH, 4));
	}

	public function testNoBonusWaveAtOmenLevelOne() : void{
		self::assertFalse(RaidWaveComposition::hasBonusWave(1));
		self::assertTrue(RaidWaveComposition::hasBonusWave(2));
		self::assertTrue(RaidWaveComposition::hasBonusWave(5));
	}

	public function testOminousBonusOnlyAppliesToFinalWave() : void{
		//normal difficulty -> 5 waves; bonus applies at wave 5 not wave 4
		$wave4 = RaidWaveComposition::totalCount(RaiderType::RAVAGER, 4, World::DIFFICULTY_NORMAL, 3);
		$wave5 = RaidWaveComposition::totalCount(RaiderType::RAVAGER, 5, World::DIFFICULTY_NORMAL, 3);
		self::assertSame(RaidWaveComposition::baseCount(RaiderType::RAVAGER, 4), $wave4);
		self::assertSame(RaidWaveComposition::baseCount(RaiderType::RAVAGER, 5) + 2, $wave5); //+2 bonus from omen level 3
	}

	public function testBonusCountScalesWithOmenLevel() : void{
		self::assertSame(0, RaidWaveComposition::bonusCount(RaiderType::RAVAGER, 1));
		self::assertSame(1, RaidWaveComposition::bonusCount(RaiderType::RAVAGER, 2));
		self::assertSame(4, RaidWaveComposition::bonusCount(RaiderType::VINDICATOR, 5));
		self::assertSame(0, RaidWaveComposition::bonusCount(RaiderType::PILLAGER, 5)); //pillagers not reinforced
	}

	public function testTotalRaidersIncreasesWithDifficulty() : void{
		$easy = RaidWaveComposition::totalRaiders(World::DIFFICULTY_EASY, 1);
		$hard = RaidWaveComposition::totalRaiders(World::DIFFICULTY_HARD, 1);
		self::assertGreaterThan($easy, $hard);
		self::assertSame(0, RaidWaveComposition::totalRaiders(World::DIFFICULTY_PEACEFUL, 1));
	}

	public function testOminousRaidHasMoreRaidersThanNormal() : void{
		$normal = RaidWaveComposition::totalRaiders(World::DIFFICULTY_HARD, 1);
		$ominous = RaidWaveComposition::totalRaiders(World::DIFFICULTY_HARD, 4);
		self::assertGreaterThan($normal, $ominous);
	}
}
