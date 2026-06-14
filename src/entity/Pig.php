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

class Pig extends Animal{

	public static function getNetworkTypeId() : string{ return EntityIds::PIG; }

	protected function getBreedingSpecies() : ?string{ return BreedingHelper::PIG; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo($this->isBaby() ? 0.45 : 0.9, $this->isBaby() ? 0.45 : 0.9);
	}

	protected function getDefaultMaxHealth() : int{
		return 10;
	}

	public function getName() : string{
		return "Pig";
	}

	public function getDrops() : array{
		$count = mt_rand(1, 3);
		return [$this->isOnFire() ? VanillaItems::COOKED_PORKCHOP()->setCount($count) : VanillaItems::RAW_PORKCHOP()->setCount($count)];
	}

	public function getXpDropAmount() : int{
		return $this->isBaby() ? 0 : mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::PIG_SPAWN_EGG();
	}
}
