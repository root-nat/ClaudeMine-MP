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

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\utils\Random;

/**
 * Hand-rolled loot for a pillager outpost chest: utilitarian raider supplies. Deterministic for a given Random.
 */
final class PillagerOutpostLoot{

	private function __construct(){
	}

	/**
	 * @return Item[]
	 */
	public static function roll(Random $random) : array{
		$items = [];
		$rolls = 3 + $random->nextBoundedInt(5); //3..7 stacks
		for($i = 0; $i < $rolls; ++$i){
			$items[] = self::pick($random);
		}
		return $items;
	}

	private static function pick(Random $random) : Item{
		$roll = $random->nextBoundedInt(100);
		if($roll < 30){
			return VanillaItems::ARROW()->setCount(2 + $random->nextBoundedInt(8));
		}
		if($roll < 50){
			return VanillaItems::WHEAT()->setCount(2 + $random->nextBoundedInt(5));
		}
		if($roll < 66){
			return VanillaItems::POTATO()->setCount(2 + $random->nextBoundedInt(5));
		}
		if($roll < 80){
			return VanillaItems::IRON_INGOT()->setCount(1 + $random->nextBoundedInt(3));
		}
		if($roll < 92){
			return VanillaItems::EXPERIENCE_BOTTLE()->setCount(1 + $random->nextBoundedInt(3));
		}
		return VanillaItems::GOLD_INGOT()->setCount(1 + $random->nextBoundedInt(2));
	}
}
