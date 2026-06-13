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

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;

class Chicken extends Animal{

	public static function getNetworkTypeId() : string{ return EntityIds::CHICKEN; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo($this->isBaby() ? 0.35 : 0.7, $this->isBaby() ? 0.2 : 0.4);
	}

	protected function getDefaultMaxHealth() : int{
		return 4;
	}

	public function getName() : string{
		return "Chicken";
	}

	/**
	 * Chickens flap to slow their fall, taking no fall damage.
	 */
	protected function calculateFallDamage(float $fallDistance) : float{
		return 0.0;
	}

	public function getDrops() : array{
		$count = mt_rand(0, 2);
		$drops = [VanillaItems::FEATHER()->setCount($count)];
		$drops[] = $this->isOnFire() ? VanillaItems::COOKED_CHICKEN() : VanillaItems::RAW_CHICKEN();
		return $drops;
	}

	public function getXpDropAmount() : int{
		return $this->isBaby() ? 0 : mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::CHICKEN_SPAWN_EGG();
	}
}
