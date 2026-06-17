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

use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\utils\Random;

/**
 * Hand-rolled loot for a ruined portal chest: portal/iron-age supplies. Deterministic for a given Random.
 */
final class RuinedPortalLoot{

	private function __construct(){
	}

	/**
	 * @return Item[]
	 */
	public static function roll(Random $random) : array{
		$items = [];
		$rolls = 3 + $random->nextBoundedInt(4); //3..6 stacks
		for($i = 0; $i < $rolls; ++$i){
			$items[] = self::pick($random);
		}
		return $items;
	}

	private static function pick(Random $random) : Item{
		$roll = $random->nextBoundedInt(100);
		if($roll < 26){
			return VanillaItems::GOLD_NUGGET()->setCount(2 + $random->nextBoundedInt(8));
		}
		if($roll < 44){
			return VanillaItems::FLINT()->setCount(1 + $random->nextBoundedInt(3));
		}
		if($roll < 60){
			return VanillaItems::IRON_INGOT()->setCount(1 + $random->nextBoundedInt(3));
		}
		if($roll < 74){
			return VanillaBlocks::OBSIDIAN()->asItem()->setCount(1 + $random->nextBoundedInt(2));
		}
		if($roll < 85){
			return VanillaItems::GOLD_INGOT()->setCount(1 + $random->nextBoundedInt(2));
		}
		if($roll < 93){
			return VanillaItems::FIRE_CHARGE()->setCount(1 + $random->nextBoundedInt(2));
		}
		if($roll < 98){
			return VanillaItems::FLINT_AND_STEEL();
		}
		return VanillaItems::GOLDEN_APPLE(); //rare
	}
}
