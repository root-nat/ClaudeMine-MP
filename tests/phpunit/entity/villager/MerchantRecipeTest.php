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

namespace pocketmine\entity\villager;

use PHPUnit\Framework\TestCase;
use pocketmine\item\VanillaItems;

class MerchantRecipeTest extends TestCase{

	private function recipe(int $emeralds = 5, int $maxUses = 12) : MerchantRecipe{
		return new MerchantRecipe(
			VanillaItems::EMERALD()->setCount($emeralds),
			null,
			VanillaItems::IRON_PICKAXE(),
			$maxUses,
			0.05
		);
	}

	public function testBaselinePriceIsBaseCount() : void{
		$recipe = $this->recipe(5);
		self::assertSame(5, $recipe->getAdjustedFirstBuy()->getCount());
	}

	public function testOutOfStockAfterMaxUses() : void{
		$recipe = $this->recipe(5, 3);
		self::assertFalse($recipe->isOutOfStock());
		$recipe->onTrade();
		$recipe->onTrade();
		$recipe->onTrade();
		self::assertTrue($recipe->isOutOfStock());
	}

	public function testHeavyUseRaisesDemandAndPriceAfterRestock() : void{
		$recipe = $this->recipe(10, 4);
		for($i = 0; $i < 4; ++$i){
			$recipe->onTrade();
		}
		$recipe->restock();
		//demand = 0 + 2*4 - 4 = 4; adjustment = floor(10 * 0.05 * 4) = 2
		self::assertSame(4, $recipe->getDemand());
		self::assertSame(12, $recipe->getAdjustedFirstBuy()->getCount());
		self::assertSame(0, $recipe->getUses(), "Restock resets uses");
	}

	public function testLightUseLowersDemand() : void{
		$recipe = $this->recipe(10, 12);
		$recipe->onTrade(); //only 1 use of 12
		$recipe->restock();
		//demand = max(0, 0 + 2*1 - 12) = 0
		self::assertSame(0, $recipe->getDemand());
	}

	public function testSpecialPriceDiscount() : void{
		$recipe = $this->recipe(5);
		$recipe->setSpecialPrice(-3);
		self::assertSame(2, $recipe->getAdjustedFirstBuy()->getCount());
	}

	public function testSpecialPriceNeverBelowOne() : void{
		$recipe = $this->recipe(5);
		$recipe->setSpecialPrice(-100);
		self::assertSame(1, $recipe->getAdjustedFirstBuy()->getCount());
	}

	public function testSpecialPriceSurcharge() : void{
		$recipe = $this->recipe(5);
		$recipe->setSpecialPrice(10);
		self::assertSame(15, $recipe->getAdjustedFirstBuy()->getCount());
	}

	public function testPriceClampedToMaxStack() : void{
		$recipe = new MerchantRecipe(VanillaItems::EMERALD()->setCount(60), null, VanillaItems::DIAMOND(), 12, 0.05);
		$recipe->setSpecialPrice(40);
		self::assertSame(64, $recipe->getAdjustedFirstBuy()->getCount()); //emerald max stack 64
	}

	public function testItemsAreDefensivelyCopied() : void{
		$recipe = $this->recipe(5);
		$recipe->getAdjustedFirstBuy()->setCount(99);
		self::assertSame(5, $recipe->getAdjustedFirstBuy()->getCount(), "Mutating a returned item must not affect the recipe");
	}
}
