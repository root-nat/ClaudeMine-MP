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
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\VanillaItems;

class AnvilHelperTest extends TestCase{

	public function testRenameOnlyCostsOne() : void{
		$result = AnvilHelper::tryCombine(VanillaItems::DIAMOND_SWORD(), VanillaItems::AIR(), "Excalibur");
		self::assertNotNull($result);
		self::assertSame("Excalibur", $result->result->getCustomName());
		self::assertSame(1, $result->cost);
	}

	public function testNoChangeReturnsNull() : void{
		self::assertNull(AnvilHelper::tryCombine(VanillaItems::DIAMOND_SWORD(), VanillaItems::AIR(), null));
		//renaming to the existing (empty) name is a no-op
		self::assertNull(AnvilHelper::tryCombine(VanillaItems::DIAMOND_SWORD(), VanillaItems::AIR(), ""));
	}

	public function testRepairItemWithSameItem() : void{
		$damaged = VanillaItems::DIAMOND_PICKAXE();
		$damaged->setDamage(1000);
		$sacrifice = VanillaItems::DIAMOND_PICKAXE();
		$sacrifice->setDamage(500);

		$result = AnvilHelper::tryCombine($damaged, $sacrifice, null);
		self::assertNotNull($result);
		self::assertInstanceOf(\pocketmine\item\Durable::class, $result->result);
		self::assertLessThan(1000, $result->result->getDamage()); //repaired
		self::assertGreaterThanOrEqual(2, $result->cost);
	}

	public function testEnchantFromBook() : void{
		$pick = VanillaItems::DIAMOND_PICKAXE();
		$book = VanillaItems::ENCHANTED_BOOK();
		$book->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 2));

		$result = AnvilHelper::tryCombine($pick, $book, null);
		self::assertNotNull($result);
		self::assertSame(2, $result->result->getEnchantmentLevel(VanillaEnchantments::EFFICIENCY()));
		self::assertGreaterThan(0, $result->cost);
	}

	public function testSameLevelEnchantsCombineToNextLevel() : void{
		$sword = VanillaItems::DIAMOND_SWORD();
		$sword->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 1));
		$other = VanillaItems::DIAMOND_SWORD();
		$other->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 1));

		$result = AnvilHelper::tryCombine($sword, $other, null);
		self::assertNotNull($result);
		self::assertSame(2, $result->result->getEnchantmentLevel(VanillaEnchantments::SHARPNESS()));
	}

	public function testPriorWorkPenaltyGrows() : void{
		$pick = VanillaItems::DIAMOND_PICKAXE();
		$book = VanillaItems::ENCHANTED_BOOK();
		$book->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 1));

		$first = AnvilHelper::tryCombine($pick, $book, null);
		self::assertNotNull($first);
		self::assertSame(0, AnvilHelper::getRepairCost($pick)); //unchanged input
		self::assertGreaterThan(0, AnvilHelper::getRepairCost($first->result)); //result carries a higher penalty

		//re-working the result is more expensive than the first time
		$book2 = VanillaItems::ENCHANTED_BOOK();
		$book2->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 1));
		$second = AnvilHelper::tryCombine($first->result, $book2, null);
		self::assertNotNull($second);
		self::assertGreaterThan($first->cost, $second->cost);
	}
}
