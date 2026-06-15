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

use pocketmine\entity\ai\goal\MeleeAttackGoal;
use pocketmine\entity\ai\goal\RandomStrollGoal;
use pocketmine\entity\ai\sensor\HurtBySensor;
use pocketmine\entity\ai\sensor\ZoglinTargetSensor;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

/**
 * The mindless undead form of a {@link Hoglin} that has lingered outside the Nether. It attacks anything living - players,
 * villagers, animals, even other monsters - sparing only fellow zoglins and creepers, and gores like the hoglin it was.
 * It fears nothing, can't breed, and (already zombified) never converts again.
 */
class Zoglin extends Hoglin{

	public static function getNetworkTypeId() : string{ return EntityIds::ZOGLIN; }

	public function getName() : string{
		return "Zoglin";
	}

	protected function getBreedingSpecies() : ?string{
		return null; //zoglins don't breed
	}

	protected function canZombify() : bool{
		return false; //already zombified
	}

	protected function registerBehaviour() : void{
		//hostile to every living thing in range (ZoglinTargetSensor), goring with the inherited hoglin attack
		$this->addSensor(new HurtBySensor());
		$this->addSensor(new ZoglinTargetSensor($this->getFollowRange()));

		$this->addGoal(1, new MeleeAttackGoal());
		$this->addGoal(7, new RandomStrollGoal());
	}

	public function getDrops() : array{
		return [];
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::ZOGLIN_SPAWN_EGG();
	}
}
