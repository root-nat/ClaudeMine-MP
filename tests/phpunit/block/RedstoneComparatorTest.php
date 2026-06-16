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

namespace pocketmine\block;

use PHPUnit\Framework\TestCase;
use pocketmine\inventory\SimpleInventory;
use pocketmine\item\VanillaItems;

class RedstoneComparatorTest extends TestCase{

	public function testEmptyContainerSignal() : void{
		self::assertSame(0, RedstoneComparator::calculateContainerSignal(new SimpleInventory(27)));
	}

	public function testSingleItemGivesMinimumSignal() : void{
		$inventory = new SimpleInventory(27);
		$inventory->setItem(0, VanillaItems::DIAMOND());
		self::assertSame(1, RedstoneComparator::calculateContainerSignal($inventory));
	}

	public function testFullContainerGivesMaximumSignal() : void{
		$inventory = new SimpleInventory(27);
		for($i = 0; $i < 27; ++$i){
			$inventory->setItem($i, VanillaItems::DIAMOND()->setCount(64));
		}
		self::assertSame(15, RedstoneComparator::calculateContainerSignal($inventory));
	}

	public function testHalfFullContainer() : void{
		$inventory = new SimpleInventory(2);
		$inventory->setItem(0, VanillaItems::DIAMOND()->setCount(64));
		$signal = RedstoneComparator::calculateContainerSignal($inventory);
		self::assertSame(8, $signal);
	}

	public function testUnstackableItemCountsAsFullSlot() : void{
		$inventory = new SimpleInventory(1);
		$inventory->setItem(0, VanillaItems::DIAMOND_SWORD());
		self::assertSame(15, RedstoneComparator::calculateContainerSignal($inventory));
	}
}
