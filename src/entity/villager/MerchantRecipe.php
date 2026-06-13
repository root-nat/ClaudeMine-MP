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

namespace pocketmine\entity\villager;

use pocketmine\item\Item;
use function floor;
use function max;
use function min;

/**
 * A single villager trade offer with vanilla price dynamics: demand raises the buy cost as the trade is used,
 * restocking relaxes it, and a reputation/hero special price discounts or surcharges it. Pure logic - the price model
 * is fully unit-testable; the actual exchange UI is handled separately.
 */
final class MerchantRecipe{

	private int $uses = 0;
	private int $demand = 0;
	private int $specialPriceDiff = 0;

	public function __construct(
		private Item $firstBuy,
		private ?Item $secondBuy,
		private Item $sell,
		private int $maxUses = 12,
		private float $priceMultiplier = 0.05,
		private int $rewardExp = 0
	){}

	public function getFirstBuy() : Item{
		return clone $this->firstBuy;
	}

	public function getSecondBuy() : ?Item{
		return $this->secondBuy === null ? null : clone $this->secondBuy;
	}

	public function getSell() : Item{
		return clone $this->sell;
	}

	public function getUses() : int{
		return $this->uses;
	}

	public function getMaxUses() : int{
		return $this->maxUses;
	}

	public function getDemand() : int{
		return $this->demand;
	}

	public function getRewardExp() : int{
		return $this->rewardExp;
	}

	public function getSpecialPriceDiff() : int{
		return $this->specialPriceDiff;
	}

	/**
	 * Sets the reputation/hero-of-the-village price adjustment (negative = discount, positive = surcharge).
	 */
	public function setSpecialPrice(int $diff) : void{
		$this->specialPriceDiff = $diff;
	}

	public function isOutOfStock() : bool{
		return $this->uses >= $this->maxUses;
	}

	/**
	 * Returns the first buy item with its count adjusted for current demand and special price, clamped to a usable range.
	 */
	public function getAdjustedFirstBuy() : Item{
		$base = $this->firstBuy->getCount();
		$demandAdjustment = (int) floor($base * $this->priceMultiplier * max(0, $this->demand));
		$count = $base + $demandAdjustment + $this->specialPriceDiff;
		$count = max(1, min($this->firstBuy->getMaxStackSize(), $count));

		$item = clone $this->firstBuy;
		$item->setCount($count);
		return $item;
	}

	/**
	 * Records one use of this trade.
	 */
	public function onTrade() : void{
		++$this->uses;
	}

	/**
	 * Restocks the trade, updating demand from how heavily it was used since the last restock, then resetting uses.
	 */
	public function restock() : void{
		$this->demand = max(0, $this->demand + (2 * $this->uses) - $this->maxUses);
		$this->uses = 0;
	}
}
