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

use pocketmine\block\Cactus;
use pocketmine\block\Cake;
use pocketmine\block\Flower;
use pocketmine\block\HayBale;
use pocketmine\block\Leaves;
use pocketmine\block\Melon;
use pocketmine\block\Pumpkin;
use pocketmine\block\Sapling;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use function min;

/**
 * Pure composter logic, isolated from the live World so it can be unit-tested: the fill-level transitions and the vanilla
 * per-item compost chances. {@link \pocketmine\block\Composter} drives the world-facing behaviour and delegates here.
 *
 * A composter fills from level 0 to {@link self::READY_LEVEL}. Right-clicking with a compostable item always consumes one
 * item; with probability {@link self::getCompostChance()} the fill level rises by one. At the ready level, interacting
 * harvests one bone meal and resets the composter to empty.
 *
 * The compost-chance table is a representative (not exhaustive) subset of the vanilla table covering the five chance
 * tiers; more items can be added without changing the mechanics.
 */
final class ComposterLogic{

	public const EMPTY_LEVEL = 0;
	public const READY_LEVEL = 8;

	/**
	 * @var array<int, float>|null
	 * @phpstan-var array<int, float>|null
	 */
	private static ?array $itemChances = null;

	private function __construct(){
		//NOOP
	}

	public static function isEmpty(int $level) : bool{
		return $level <= self::EMPTY_LEVEL;
	}

	public static function isReady(int $level) : bool{
		return $level >= self::READY_LEVEL;
	}

	/**
	 * Whether the composter can still accept items (i.e. it is not yet ready for harvest).
	 */
	public static function canAccept(int $level) : bool{
		return $level < self::READY_LEVEL;
	}

	/**
	 * The fill level after a successful compost, capped at the ready level.
	 */
	public static function levelAfterCompost(int $level) : int{
		return min(self::READY_LEVEL, $level + 1);
	}

	/**
	 * Whether a compost attempt with the given chance succeeds for a roll in [0, 1).
	 */
	public static function rollSucceeds(float $chance, float $roll) : bool{
		return $roll < $chance;
	}

	/**
	 * @return array<int, float> item type id => compost chance
	 */
	private static function itemChances() : array{
		return self::$itemChances ??= [
			VanillaItems::WHEAT_SEEDS()->getTypeId() => 0.30,
			VanillaItems::BEETROOT_SEEDS()->getTypeId() => 0.30,
			VanillaItems::MELON_SEEDS()->getTypeId() => 0.30,
			VanillaItems::PUMPKIN_SEEDS()->getTypeId() => 0.30,
			VanillaItems::SWEET_BERRIES()->getTypeId() => 0.30,
			VanillaItems::MELON()->getTypeId() => 0.50, //melon slice
			VanillaItems::APPLE()->getTypeId() => 0.65,
			VanillaItems::CARROT()->getTypeId() => 0.65,
			VanillaItems::POTATO()->getTypeId() => 0.65,
			VanillaItems::BEETROOT()->getTypeId() => 0.65,
			VanillaItems::WHEAT()->getTypeId() => 0.65,
			VanillaItems::BAKED_POTATO()->getTypeId() => 0.85,
			VanillaItems::BREAD()->getTypeId() => 0.85,
			VanillaItems::COOKIE()->getTypeId() => 0.85,
			VanillaItems::PUMPKIN_PIE()->getTypeId() => 1.00,
		];
	}

	/**
	 * The vanilla compost chance for an item, or 0.0 if it cannot be composted.
	 */
	public static function getCompostChance(Item $item) : float{
		if($item->isNull()){
			return 0.0;
		}

		$block = $item->getBlock();
		$blockChance = match(true){
			$block instanceof Leaves, $block instanceof Sapling => 0.30,
			$block instanceof Cactus => 0.50,
			$block instanceof Flower, $block instanceof Melon, $block instanceof Pumpkin => 0.65,
			$block instanceof HayBale => 0.85,
			$block instanceof Cake => 1.00,
			default => 0.0
		};
		if($blockChance > 0.0){
			return $blockChance;
		}

		return self::itemChances()[$item->getTypeId()] ?? 0.0;
	}

	public static function isCompostable(Item $item) : bool{
		return self::getCompostChance($item) > 0.0;
	}
}
