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

use pocketmine\item\Durable;
use pocketmine\item\EnchantedBook;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\IncompatibleEnchantmentRegistry;
use pocketmine\item\enchantment\Rarity;
use pocketmine\item\Item;
use function floor;
use function max;
use function min;

/**
 * Pure vanilla anvil logic, isolated from the live World/network so it can be unit-tested: computes the result item and
 * its experience-level cost for repairing, combining enchantments, and renaming. {@link \pocketmine\block\inventory\AnvilInventory}
 * uses this to preview the output, and the network layer uses it to validate and charge the operation.
 *
 * Prior-work penalty is stored in the item's "RepairCost" NBT tag (vanilla parity): each anvil use doubles it, making
 * repeatedly-worked items progressively more expensive.
 */
final class AnvilHelper{

	public const TAG_REPAIR_COST = "RepairCost";
	public const RENAME_COST = 1;
	public const DURABILITY_RESTORE_FRACTION = 0.12;
	/** Survival anvils refuse a combination whose cost reaches this many levels ("Too Expensive!"). */
	public const TOO_EXPENSIVE_COST = 40;

	private function __construct(){
		//NOOP
	}

	public static function getRepairCost(Item $item) : int{
		return $item->getNamedTag()->getInt(self::TAG_REPAIR_COST, 0);
	}

	private static function setRepairCost(Item $item, int $cost) : void{
		$tag = $item->getNamedTag();
		$tag->setInt(self::TAG_REPAIR_COST, $cost);
		$item->setNamedTag($tag);
	}

	/**
	 * The anvil cost multiplier for an enchantment of a given rarity when applied from an item; halved (min 1) when the
	 * source is an enchanted book.
	 */
	private static function anvilMultiplier(int $rarity) : int{
		return match($rarity){
			Rarity::COMMON => 1,
			Rarity::UNCOMMON => 2,
			Rarity::RARE => 4,
			Rarity::MYTHIC => 8,
			default => 1
		};
	}

	/**
	 * Computes the result of combining $base (left slot) with $material (right slot) and optionally renaming it. Returns
	 * null when nothing would change. The returned cost is the experience-level price; callers enforce the survival
	 * "Too Expensive!" cap via {@link self::TOO_EXPENSIVE_COST}.
	 */
	public static function tryCombine(Item $base, Item $material, ?string $newName) : ?AnvilResult{
		if($base->isNull()){
			return null;
		}

		$result = clone $base;
		$baseRepairCost = self::getRepairCost($base);
		$materialRepairCost = $material->isNull() ? 0 : self::getRepairCost($material);
		$cost = $baseRepairCost + $materialRepairCost;
		$changed = false;

		if(!$material->isNull()){
			$mergeCost = self::applyMaterial($result, $base, $material);
			if($mergeCost !== null){
				$cost += $mergeCost;
				$changed = true;
			}
		}

		if($newName !== null){
			$current = $base->hasCustomName() ? $base->getCustomName() : "";
			if($newName !== $current){
				$result->setCustomName($newName);
				$cost += self::RENAME_COST;
				$changed = true;
			}
		}

		if(!$changed){
			return null;
		}

		//bump the prior-work penalty so the item costs more next time
		self::setRepairCost($result, max($baseRepairCost, $materialRepairCost) * 2 + 1);

		return new AnvilResult($result, $cost);
	}

	/**
	 * Applies durability repair and enchantment merging from $material onto $result. Returns the added cost, or null if
	 * the material has no applicable effect (so only a rename could still happen).
	 */
	private static function applyMaterial(Item $result, Item $base, Item $material) : ?int{
		$cost = 0;
		$applied = false;
		$isBook = $material instanceof EnchantedBook;
		$sameType = $base->getTypeId() === $material->getTypeId();

		//durability repair by sacrificing a second item of the same type (vanilla adds a 12% bonus)
		if(!$isBook && $sameType && $base instanceof Durable && $material instanceof Durable && $result instanceof Durable){
			$maxDurability = $base->getMaxDurability();
			$baseRemaining = $maxDurability - $base->getDamage();
			$materialRemaining = $material->getMaxDurability() - $material->getDamage();
			$bonus = (int) floor($maxDurability * self::DURABILITY_RESTORE_FRACTION);
			$newRemaining = min($maxDurability, $baseRemaining + $materialRemaining + $bonus);
			$newDamage = $maxDurability - $newRemaining;
			if($newDamage < $base->getDamage()){
				$result->setDamage($newDamage);
				$cost += 2;
				$applied = true;
			}
		}

		//enchantment merge from a book or a same-type enchanted item
		if($isBook || $sameType){
			foreach($material->getEnchantments() as $instance){
				$enchantment = $instance->getType();
				$materialLevel = $instance->getLevel();
				$baseLevel = $result->getEnchantmentLevel($enchantment);

				$newLevel = ($baseLevel === $materialLevel) ? $materialLevel + 1 : max($baseLevel, $materialLevel);
				$newLevel = min($newLevel, $enchantment->getMaxLevel());
				if($newLevel <= 0){
					continue;
				}

				//skip enchantments that conflict with what the result already carries
				$compatible = true;
				foreach($result->getEnchantments() as $existing){
					if($existing->getType() !== $enchantment && !IncompatibleEnchantmentRegistry::getInstance()->areCompatible($existing->getType(), $enchantment)){
						$compatible = false;
						break;
					}
				}
				if(!$compatible){
					continue;
				}

				$multiplier = self::anvilMultiplier($enchantment->getRarity());
				if($isBook){
					$multiplier = max(1, (int) floor($multiplier / 2));
				}
				$cost += $newLevel * $multiplier;

				if($newLevel !== $baseLevel){
					$result->addEnchantment(new EnchantmentInstance($enchantment, $newLevel));
					$applied = true;
				}
			}
		}

		return $applied ? $cost : null;
	}
}
