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

/**
 * Aquatic zombie variant: like a zombie but, being waterlogged, it never burns in daylight and drops the occasional
 * nautilus shell instead of a zombie's salvage.
 */
class Drowned extends Zombie{

	public static function getNetworkTypeId() : string{ return EntityIds::DROWNED; }

	public function getName() : string{
		return "Drowned";
	}

	protected function burnsInDaylight() : bool{
		return false; //the drowned are waterlogged - sunlight doesn't dry them out
	}

	public function canBreathe() : bool{
		return true; //the drowned breathe water as happily as air, so they never take drowning damage
	}

	protected function convertsInWater() : bool{
		return false; //already drowned - it stays a drowned underwater
	}

	public function getDrops() : array{
		$drops = [VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(0, 2))];
		if(mt_rand(0, 99) < 3){
			$drops[] = VanillaItems::NAUTILUS_SHELL();
		}
		return $drops;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::DROWNED_SPAWN_EGG();
	}
}
