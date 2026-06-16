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
 * Hand-rolled weighted loot for a bastion treasure chest (PMMP has no loot-table registry). Gold-heavy with a thin tail
 * of crying obsidian and netherite scrap, in the spirit of the vanilla bastion treasure room.
 */
final class BastionLoot{

	private function __construct(){
	}

	/**
	 * Rolls a handful of loot stacks. Deterministic for a given Random.
	 *
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
		if($roll < 30){
			return VanillaItems::GOLD_NUGGET()->setCount(2 + $random->nextBoundedInt(8));
		}
		if($roll < 55){
			return VanillaItems::GOLD_INGOT()->setCount(1 + $random->nextBoundedInt(4));
		}
		if($roll < 70){
			return VanillaItems::IRON_INGOT()->setCount(1 + $random->nextBoundedInt(3));
		}
		if($roll < 82){
			return VanillaBlocks::GILDED_BLACKSTONE()->asItem()->setCount(1 + $random->nextBoundedInt(2));
		}
		if($roll < 90){
			return VanillaItems::MAGMA_CREAM()->setCount(1 + $random->nextBoundedInt(2));
		}
		if($roll < 96){
			return VanillaBlocks::CRYING_OBSIDIAN()->asItem()->setCount(1 + $random->nextBoundedInt(2));
		}
		if($roll < 99){
			return VanillaBlocks::GOLD()->asItem(); //gold block - rare
		}
		return VanillaItems::NETHERITE_SCRAP(); //very rare
	}
}
