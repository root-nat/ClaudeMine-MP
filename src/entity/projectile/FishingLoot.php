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

namespace pocketmine\entity\projectile;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

/**
 * Pure fishing loot table (no Luck of the Sea): a catch is mostly fish, sometimes junk, rarely treasure. Takes the random
 * rolls as parameters so it stays deterministic and unit-testable; {@link FishingHook} supplies live rolls.
 */
final class FishingLoot{

	private function __construct(){
		//NOOP
	}

	/**
	 * @param float $categoryRoll [0,1) selects fish/junk/treasure
	 * @param float $itemRoll     [0,1) selects the item within the chosen category
	 */
	public static function roll(float $categoryRoll, float $itemRoll) : Item{
		if($categoryRoll < 0.85){
			return self::fish($itemRoll);
		}
		if($categoryRoll < 0.95){
			return self::junk($itemRoll);
		}
		return self::treasure($itemRoll);
	}

	private static function fish(float $r) : Item{
		return match(true){
			$r < 0.60 => VanillaItems::RAW_FISH(),
			$r < 0.85 => VanillaItems::RAW_SALMON(),
			$r < 0.98 => VanillaItems::PUFFERFISH(),
			default => VanillaItems::CLOWNFISH(),
		};
	}

	private static function junk(float $r) : Item{
		return match(true){
			$r < 0.30 => VanillaItems::STICK(),
			$r < 0.50 => VanillaItems::STRING(),
			$r < 0.70 => VanillaItems::BOWL(),
			$r < 0.88 => VanillaItems::ROTTEN_FLESH(),
			default => VanillaItems::INK_SAC()->setCount(10),
		};
	}

	private static function treasure(float $r) : Item{
		return match(true){
			$r < 0.50 => VanillaItems::NAME_TAG(),
			default => VanillaItems::LEATHER_BOOTS(),
		};
	}
}
