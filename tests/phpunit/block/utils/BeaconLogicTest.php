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
use pocketmine\data\bedrock\EffectIds;
use pocketmine\item\VanillaItems;

class BeaconLogicTest extends TestCase{

	public function testPyramidLevelCountsConsecutiveCompleteLayers() : void{
		self::assertSame(0, BeaconLogic::pyramidLevel([false, false, false, false]));
		self::assertSame(1, BeaconLogic::pyramidLevel([true, false, false, false]));
		self::assertSame(2, BeaconLogic::pyramidLevel([true, true, false, false]));
		self::assertSame(4, BeaconLogic::pyramidLevel([true, true, true, true]));
		//a gap stops counting even if lower layers are complete
		self::assertSame(1, BeaconLogic::pyramidLevel([true, false, true, true]));
	}

	public function testEffectRangePerLevel() : void{
		self::assertSame(20, BeaconLogic::effectRange(1));
		self::assertSame(30, BeaconLogic::effectRange(2));
		self::assertSame(40, BeaconLogic::effectRange(3));
		self::assertSame(50, BeaconLogic::effectRange(4));
	}

	public function testEffectDurationGrowsWithLevel() : void{
		self::assertSame((9 + 1 * 2) * 20, BeaconLogic::effectDurationTicks(1));
		self::assertGreaterThan(BeaconLogic::effectDurationTicks(1), BeaconLogic::effectDurationTicks(4));
	}

	public function testValidPaymentItems() : void{
		self::assertTrue(BeaconLogic::isValidPayment(VanillaItems::IRON_INGOT()));
		self::assertTrue(BeaconLogic::isValidPayment(VanillaItems::GOLD_INGOT()));
		self::assertTrue(BeaconLogic::isValidPayment(VanillaItems::EMERALD()));
		self::assertTrue(BeaconLogic::isValidPayment(VanillaItems::DIAMOND()));
		self::assertTrue(BeaconLogic::isValidPayment(VanillaItems::NETHERITE_INGOT()));
		self::assertFalse(BeaconLogic::isValidPayment(VanillaItems::APPLE()));
		self::assertFalse(BeaconLogic::isValidPayment(VanillaItems::AIR()));
	}

	public function testAllowedPrimaryEffectsUnlockByLevel() : void{
		self::assertFalse(BeaconLogic::isAllowedPrimary(0, EffectIds::SPEED));
		self::assertTrue(BeaconLogic::isAllowedPrimary(1, EffectIds::SPEED));
		self::assertTrue(BeaconLogic::isAllowedPrimary(1, EffectIds::HASTE));
		self::assertFalse(BeaconLogic::isAllowedPrimary(1, EffectIds::RESISTANCE));
		self::assertTrue(BeaconLogic::isAllowedPrimary(2, EffectIds::RESISTANCE));
		self::assertTrue(BeaconLogic::isAllowedPrimary(2, EffectIds::JUMP_BOOST));
		self::assertFalse(BeaconLogic::isAllowedPrimary(2, EffectIds::STRENGTH));
		self::assertTrue(BeaconLogic::isAllowedPrimary(3, EffectIds::STRENGTH));
		self::assertTrue(BeaconLogic::isAllowedPrimary(4, EffectIds::STRENGTH));
	}

	public function testSecondaryOnlyAtLevelFour() : void{
		self::assertTrue(BeaconLogic::isAllowedSecondary(4, EffectIds::SPEED, 0)); //none is always fine
		self::assertFalse(BeaconLogic::isAllowedSecondary(3, EffectIds::SPEED, EffectIds::REGENERATION));
		self::assertTrue(BeaconLogic::isAllowedSecondary(4, EffectIds::SPEED, EffectIds::REGENERATION));
		self::assertTrue(BeaconLogic::isAllowedSecondary(4, EffectIds::SPEED, EffectIds::SPEED)); //same = upgrade
		self::assertFalse(BeaconLogic::isAllowedSecondary(4, EffectIds::SPEED, EffectIds::HASTE)); //not regen, not primary
	}

	public function testSecondaryUpgradeDetection() : void{
		self::assertTrue(BeaconLogic::secondaryUpgradesPrimary(4, EffectIds::SPEED, EffectIds::SPEED));
		self::assertFalse(BeaconLogic::secondaryUpgradesPrimary(4, EffectIds::SPEED, EffectIds::REGENERATION));
		self::assertFalse(BeaconLogic::secondaryUpgradesPrimary(3, EffectIds::SPEED, EffectIds::SPEED));
	}
}
