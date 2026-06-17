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
 * Hand-rolled weighted loot for a desert temple chest (PMMP has no loot-table registry), in the spirit of the vanilla
 * desert-pyramid treasure: gold/iron filler with a thin tail of diamonds, emeralds, a saddle and the rare enchanted
 * golden apple. Deterministic for a given Random.
 */
final class DesertTempleLoot{

	private function __construct(){
	}

	/**
	 * @return Item[]
	 */
	public static function roll(Random $random) : array{
		$items = [];
		$rolls = 4 + $random->nextBoundedInt(5); //4..8 stacks
		for($i = 0; $i < $rolls; ++$i){
			$items[] = self::pick($random);
		}
		return $items;
	}

	private static function pick(Random $random) : Item{
		$roll = $random->nextBoundedInt(100);
		if($roll < 22){
			return VanillaItems::ROTTEN_FLESH()->setCount(1 + $random->nextBoundedInt(6));
		}
		if($roll < 40){
			return VanillaItems::BONE()->setCount(1 + $random->nextBoundedInt(6));
		}
		if($roll < 55){
			return VanillaItems::GOLD_NUGGET()->setCount(2 + $random->nextBoundedInt(8));
		}
		if($roll < 68){
			return VanillaItems::GOLD_INGOT()->setCount(1 + $random->nextBoundedInt(4));
		}
		if($roll < 78){
			return VanillaItems::IRON_INGOT()->setCount(1 + $random->nextBoundedInt(4));
		}
		if($roll < 86){
			return VanillaItems::GUNPOWDER()->setCount(1 + $random->nextBoundedInt(4));
		}
		if($roll < 92){
			return VanillaItems::EMERALD()->setCount(1 + $random->nextBoundedInt(2));
		}
		if($roll < 96){
			return VanillaItems::DIAMOND()->setCount(1 + $random->nextBoundedInt(2));
		}
		if($roll < 99){
			return VanillaItems::SADDLE();
		}
		return VanillaItems::ENCHANTED_GOLDEN_APPLE(); //very rare
	}
}
