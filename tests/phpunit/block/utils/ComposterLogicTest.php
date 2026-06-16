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

namespace pocketmine\block\utils;

use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;

class ComposterLogicTest extends TestCase{

	public function testFillLevelStates() : void{
		self::assertTrue(ComposterLogic::isEmpty(ComposterLogic::EMPTY_LEVEL));
		self::assertFalse(ComposterLogic::isEmpty(1));
		self::assertTrue(ComposterLogic::isReady(ComposterLogic::READY_LEVEL));
		self::assertFalse(ComposterLogic::isReady(7));
		self::assertTrue(ComposterLogic::canAccept(7));
		self::assertFalse(ComposterLogic::canAccept(ComposterLogic::READY_LEVEL));
	}

	public function testLevelAfterCompostRisesAndCapsAtReady() : void{
		self::assertSame(1, ComposterLogic::levelAfterCompost(0));
		self::assertSame(8, ComposterLogic::levelAfterCompost(7)); //last fill reaches the ready level
		self::assertSame(8, ComposterLogic::levelAfterCompost(8)); //never overflows
	}

	public function testRollSucceedsBelowChance() : void{
		self::assertTrue(ComposterLogic::rollSucceeds(0.5, 0.4));
		self::assertFalse(ComposterLogic::rollSucceeds(0.5, 0.6));
		self::assertFalse(ComposterLogic::rollSucceeds(0.3, 0.3)); //roll must be strictly below
		self::assertTrue(ComposterLogic::rollSucceeds(1.0, 0.999));
	}

	public function testItemCompostChances() : void{
		self::assertEqualsWithDelta(0.30, ComposterLogic::getCompostChance(VanillaItems::WHEAT_SEEDS()), 1e-9);
		self::assertEqualsWithDelta(0.50, ComposterLogic::getCompostChance(VanillaItems::MELON()), 1e-9); //melon slice
		self::assertEqualsWithDelta(0.65, ComposterLogic::getCompostChance(VanillaItems::APPLE()), 1e-9);
		self::assertEqualsWithDelta(0.85, ComposterLogic::getCompostChance(VanillaItems::BREAD()), 1e-9);
		self::assertEqualsWithDelta(1.00, ComposterLogic::getCompostChance(VanillaItems::PUMPKIN_PIE()), 1e-9);
	}

	public function testBlockCompostChances() : void{
		self::assertEqualsWithDelta(0.30, ComposterLogic::getCompostChance(VanillaBlocks::OAK_LEAVES()->asItem()), 1e-9);
		self::assertEqualsWithDelta(0.30, ComposterLogic::getCompostChance(VanillaBlocks::OAK_SAPLING()->asItem()), 1e-9);
		self::assertEqualsWithDelta(0.50, ComposterLogic::getCompostChance(VanillaBlocks::CACTUS()->asItem()), 1e-9);
		self::assertEqualsWithDelta(0.65, ComposterLogic::getCompostChance(VanillaBlocks::DANDELION()->asItem()), 1e-9);
	}

	public function testNonCompostableItems() : void{
		self::assertSame(0.0, ComposterLogic::getCompostChance(VanillaItems::DIAMOND()));
		self::assertSame(0.0, ComposterLogic::getCompostChance(VanillaItems::AIR()));
		self::assertFalse(ComposterLogic::isCompostable(VanillaItems::DIAMOND()));
		self::assertTrue(ComposterLogic::isCompostable(VanillaItems::WHEAT_SEEDS()));
	}
}
