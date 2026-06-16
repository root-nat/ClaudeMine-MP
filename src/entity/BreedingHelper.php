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

use pocketmine\block\BlockTypeIds;
use pocketmine\item\ItemTypeIds;
use function in_array;

/**
 * Pure breeding data and timings, isolated from the live entity so they can be unit-tested: which items breed which
 * species, and the vanilla durations for love mode, the breeding cooldown, and baby grow-up. {@link Animal} drives the
 * world-facing behaviour (feeding, finding a mate, spawning the baby) and delegates the data here.
 */
final class BreedingHelper{

	public const COW = "cow";
	public const SHEEP = "sheep";
	public const PIG = "pig";
	public const CHICKEN = "chicken";
	public const OCELOT = "ocelot";
	public const RABBIT = "rabbit";
	public const FOX = "fox";
	public const HOGLIN = "hoglin";
	public const STRIDER = "strider";

	/** How long an animal stays ready to breed after being fed (30s). */
	public const IN_LOVE_TICKS = 600;
	/** Cooldown before an animal can breed again (5 min). */
	public const BREED_COOLDOWN_TICKS = 6000;
	/** How long a baby takes to grow into an adult (20 min). */
	public const BABY_GROW_TICKS = 24000;
	/** Each feeding of a baby brings its adulthood this much closer. */
	public const BABY_GROW_SPEEDUP_TICKS = 200;

	private function __construct(){
		//NOOP
	}

	/**
	 * @return int[] the item type ids that put the given species into love mode
	 */
	public static function foodsFor(string $species) : array{
		return match($species){
			self::COW, self::SHEEP => [ItemTypeIds::WHEAT],
			self::PIG => [ItemTypeIds::CARROT, ItemTypeIds::POTATO, ItemTypeIds::BEETROOT],
			self::CHICKEN => [ItemTypeIds::WHEAT_SEEDS, ItemTypeIds::BEETROOT_SEEDS, ItemTypeIds::MELON_SEEDS, ItemTypeIds::PUMPKIN_SEEDS],
			self::OCELOT => [ItemTypeIds::RAW_FISH, ItemTypeIds::RAW_SALMON],
			self::RABBIT => [ItemTypeIds::CARROT, ItemTypeIds::GOLDEN_CARROT],
			self::FOX => [ItemTypeIds::SWEET_BERRIES],
			self::HOGLIN => [ItemTypeIds::fromBlockTypeId(BlockTypeIds::CRIMSON_FUNGUS)],
			self::STRIDER => [ItemTypeIds::fromBlockTypeId(BlockTypeIds::WARPED_FUNGUS)],
			default => []
		};
	}

	public static function isFood(string $species, int $itemTypeId) : bool{
		return in_array($itemTypeId, self::foodsFor($species), true);
	}
}
