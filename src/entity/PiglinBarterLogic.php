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

use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use function count;

/**
 * The weighted loot a piglin gives back when bartered a gold ingot, mirroring the vanilla barter table (a representative
 * subset of items that exist in this build). The weighted pick and the per-item count are pure functions of two integer
 * rolls, so they can be unit-tested; the live admiring delay and the item drop are handled by {@link Piglin}.
 */
final class PiglinBarterLogic{

	private function __construct(){
		//NOOP
	}

	/**
	 * @return list<array{int, int, int, \Closure() : Item}> [weight, minCount, maxCount, item factory]
	 */
	private static function table() : array{
		return [
			[40, 8, 16, fn() : Item => VanillaBlocks::GRAVEL()->asItem()],
			[40, 1, 1, fn() : Item => VanillaBlocks::OBSIDIAN()->asItem()],
			[20, 3, 9, fn() : Item => VanillaItems::STRING()],
			[20, 5, 12, fn() : Item => VanillaItems::NETHER_QUARTZ()],
			[20, 2, 4, fn() : Item => VanillaItems::LEATHER()],
			[20, 2, 8, fn() : Item => VanillaBlocks::SOUL_SAND()->asItem()],
			[20, 5, 12, fn() : Item => VanillaItems::GLOWSTONE_DUST()],
			[10, 10, 36, fn() : Item => VanillaItems::IRON_NUGGET()],
			[10, 2, 4, fn() : Item => VanillaItems::ENDER_PEARL()],
			[10, 1, 3, fn() : Item => VanillaBlocks::CRYING_OBSIDIAN()->asItem()],
		];
	}

	public static function totalWeight() : int{
		$total = 0;
		foreach(self::table() as $entry){
			$total += $entry[0];
		}
		return $total;
	}

	/**
	 * Picks the barter table row for a weight roll (any non-negative or negative int; reduced modulo the total weight).
	 * Pure - it never builds an item - so the weighting is unit-testable on its own.
	 */
	public static function selectIndex(int $weightRoll) : int{
		$table = self::table();
		$total = self::totalWeight();
		$r = (($weightRoll % $total) + $total) % $total;
		for($i = 0; $i < count($table); ++$i){
			if($r < $table[$i][0]){
				return $i;
			}
			$r -= $table[$i][0];
		}
		return 0; //unreachable: $r is always < $total
	}

	/**
	 * Returns the barter reward for the given rolls. $weightRoll selects the item by weight; $countRoll picks its stack
	 * size within that item's range. Both are reduced modulo their range, so any integers are valid input.
	 */
	public static function roll(int $weightRoll, int $countRoll) : Item{
		$entry = self::table()[self::selectIndex($weightRoll)];
		$span = $entry[2] - $entry[1] + 1;
		$count = $entry[1] + ((($countRoll % $span) + $span) % $span);
		return $entry[3]()->setCount($count);
	}
}
