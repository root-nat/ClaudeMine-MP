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

namespace pocketmine\world\generator\structure;

use PHPUnit\Framework\TestCase;
use pocketmine\utils\Random;
use function count;

class BastionLootTest extends TestCase{

	public function testRollIsNonEmpty() : void{
		$loot = BastionLoot::roll(new Random(123));
		self::assertGreaterThanOrEqual(4, count($loot));
		foreach($loot as $item){
			self::assertFalse($item->isNull(), "loot stacks must be real items");
			self::assertGreaterThanOrEqual(1, $item->getCount());
		}
	}

	public function testRollIsDeterministicForSameSeed() : void{
		$a = BastionLoot::roll(new Random(2024));
		$b = BastionLoot::roll(new Random(2024));
		self::assertSame(count($a), count($b));
		foreach($a as $i => $item){
			self::assertTrue($item->equals($b[$i], true, true), "same seed must yield identical loot");
		}
	}
}
