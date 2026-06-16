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

final class RaidProgressTest extends TestCase{

	public function testRaidStartSpawnsTheFirstWave() : void{
		self::assertSame(RaidAction::SPAWN_WAVE, RaidProgress::decide(0, 5, 0, 0, 120, true, false));
	}

	public function testWaitsWhileTheCurrentWaveHasLivingRaiders() : void{
		self::assertSame(RaidAction::WAIT, RaidProgress::decide(1, 5, 3, 0, 120, true, false));
	}

	public function testAClearedWaveSpawnsTheNextOne() : void{
		self::assertSame(RaidAction::SPAWN_WAVE, RaidProgress::decide(1, 5, 0, 0, 120, true, false));
	}

	public function testClearingTheFinalWaveIsVictory() : void{
		self::assertSame(RaidAction::VICTORY, RaidProgress::decide(5, 5, 0, 0, 120, true, false));
	}

	public function testAbandonmentIsDefeatEvenWithRaidersStillAlive() : void{
		self::assertSame(RaidAction::DEFEAT, RaidProgress::decide(2, 5, 4, 120, 120, false, false));
	}

	public function testJustBeforeTheAbandonmentThresholdItStillProgressesWithADefender() : void{
		self::assertSame(RaidAction::SPAWN_WAVE, RaidProgress::decide(2, 5, 0, 119, 120, true, false));
	}

	public function testAClearedFieldHoldsTheNextWaveWhileUnattended() : void{
		self::assertSame(RaidAction::WAIT, RaidProgress::decide(2, 5, 0, 50, 120, false, false));
	}

	public function testFinalWaveClearedIsVictoryEvenIfNoDefenderRemains() : void{
		self::assertSame(RaidAction::VICTORY, RaidProgress::decide(5, 5, 0, 50, 120, false, false));
	}

	public function testAFallenVillageIsDefeatEvenMidWaveWithDefenders() : void{
		self::assertSame(RaidAction::DEFEAT, RaidProgress::decide(2, 5, 4, 0, 120, true, true));
	}
}
