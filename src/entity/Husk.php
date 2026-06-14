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

use pocketmine\entity\ai\target\TargetCandidate;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

/**
 * Desert zombie variant: like a zombie but it doesn't burn in daylight and its hits inflict Hunger.
 */
class Husk extends Zombie{

	public static function getNetworkTypeId() : string{ return EntityIds::HUSK; }

	public function getName() : string{
		return "Husk";
	}

	protected function burnsInDaylight() : bool{
		return false; //husks are dried out - the sun doesn't bother them
	}

	protected function createWaterConversion(Location $location) : Zombie{
		return new Zombie($location); //a husk left in water rehydrates back into an ordinary zombie
	}

	public function attackEntity(TargetCandidate $target) : void{
		parent::attackEntity($target);
		$victim = $this->getWorld()->getEntity($target->entityId);
		if($victim instanceof Living && $victim->isAlive()){
			$victim->getEffects()->add(new EffectInstance(VanillaEffects::HUNGER(), 140, 0));
		}
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::HUSK_SPAWN_EGG();
	}
}
