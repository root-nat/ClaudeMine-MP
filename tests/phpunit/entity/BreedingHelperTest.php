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
use pocketmine\item\VanillaItems;

class BreedingHelperTest extends TestCase{

	public function testCowAndSheepEatWheatOnly() : void{
		self::assertTrue(BreedingHelper::isFood(BreedingHelper::COW, VanillaItems::WHEAT()->getTypeId()));
		self::assertTrue(BreedingHelper::isFood(BreedingHelper::SHEEP, VanillaItems::WHEAT()->getTypeId()));
		self::assertFalse(BreedingHelper::isFood(BreedingHelper::COW, VanillaItems::CARROT()->getTypeId()));
	}

	public function testPigEatsRootVegetables() : void{
		self::assertTrue(BreedingHelper::isFood(BreedingHelper::PIG, VanillaItems::CARROT()->getTypeId()));
		self::assertTrue(BreedingHelper::isFood(BreedingHelper::PIG, VanillaItems::POTATO()->getTypeId()));
		self::assertTrue(BreedingHelper::isFood(BreedingHelper::PIG, VanillaItems::BEETROOT()->getTypeId()));
		self::assertFalse(BreedingHelper::isFood(BreedingHelper::PIG, VanillaItems::WHEAT()->getTypeId()));
	}

	public function testChickenEatsSeeds() : void{
		self::assertTrue(BreedingHelper::isFood(BreedingHelper::CHICKEN, VanillaItems::WHEAT_SEEDS()->getTypeId()));
		self::assertTrue(BreedingHelper::isFood(BreedingHelper::CHICKEN, VanillaItems::PUMPKIN_SEEDS()->getTypeId()));
		self::assertFalse(BreedingHelper::isFood(BreedingHelper::CHICKEN, VanillaItems::WHEAT()->getTypeId()));
	}

	public function testUnknownSpeciesHasNoFood() : void{
		self::assertSame([], BreedingHelper::foodsFor("dragon"));
		self::assertFalse(BreedingHelper::isFood("dragon", VanillaItems::WHEAT()->getTypeId()));
	}

	public function testNonFoodItemsRejected() : void{
		self::assertFalse(BreedingHelper::isFood(BreedingHelper::COW, VanillaItems::DIAMOND()->getTypeId()));
		self::assertFalse(BreedingHelper::isFood(BreedingHelper::CHICKEN, VanillaItems::APPLE()->getTypeId()));
	}
}
