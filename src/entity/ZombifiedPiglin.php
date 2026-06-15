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

use pocketmine\entity\ai\goal\RandomStrollGoal;
use pocketmine\entity\ai\sensor\ZombifiedPiglinAngerSensor;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;

/**
 * A fire-immune undead piglin of the Nether wastes. Neutral on its own, but it never forgets a blow: striking one
 * enrages it AND, through the {@link ZombifiedPiglinAngerSensor}, every piglin of the sounder around it, who all swarm
 * the offender with their golden swords until their grudge times out. (Baby form and undead heal/harm inversion are not
 * yet modelled.) The anger machinery lives in {@link AbstractPiglin}.
 */
class ZombifiedPiglin extends AbstractPiglin{

	public static function getNetworkTypeId() : string{ return EntityIds::ZOMBIE_PIGMAN; }

	protected function getDefaultMaxHealth() : int{
		return 20;
	}

	public function getName() : string{
		return "Zombified Piglin";
	}

	protected function registerBehaviour() : void{
		//neutral by default: targeting is driven entirely by the anger sensor, not by a "hunt every player on sight" sensor
		$this->addSensor(new ZombifiedPiglinAngerSensor($this->getFollowRange()));
		$this->registerAttackGoals();
		$this->addGoal(8, new RandomStrollGoal());
	}

	public function getDrops() : array{
		$drops = [
			VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(0, 1)),
			VanillaItems::GOLD_NUGGET()->setCount(mt_rand(0, 1)),
		];
		if(mt_rand(0, 39) === 0){
			$drops[] = VanillaItems::GOLD_INGOT();
		}
		return $drops;
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::ZOMBIE_PIGMAN_SPAWN_EGG();
	}
}
