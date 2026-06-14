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
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;

class Zombie extends Monster{

	/** How long a zombie must stay underwater before it drowns into its water form (30s). */
	private const WATER_CONVERSION_TICKS = 600;

	private int $waterConversionTicks = 0;

	public static function getNetworkTypeId() : string{ return EntityIds::ZOMBIE; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6); //TODO: eye height ??
	}

	public function getName() : string{
		return "Zombie";
	}

	protected function registerAttackGoals() : void{
		$this->addGoal(1, new MeleeAttackGoal());
	}

	protected function burnsInDaylight() : bool{
		return true; //zombies catch fire in the morning sun
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		//bail once already converting/despawning so we can never spawn a second form before removal takes effect
		if($this->closed || !$this->isAlive() || $this->isFlaggedForDespawn()){
			return $hasUpdate;
		}

		if($this->convertsInWater() && $this->isUnderwater()){
			$this->waterConversionTicks += $tickDiff;
			if($this->waterConversionTicks >= self::WATER_CONVERSION_TICKS){
				$this->convertInWater();
			}
			$hasUpdate = true;
		}else{
			$this->waterConversionTicks = 0;
		}

		return $hasUpdate;
	}

	/**
	 * Whether this zombie type drowns into another form when left underwater (a zombie into a drowned, a husk back into
	 * a zombie). The drowned itself does not.
	 */
	protected function convertsInWater() : bool{
		return true;
	}

	/**
	 * The form this zombie becomes after drowning. Only called when {@link self::convertsInWater()} is true.
	 */
	protected function createWaterConversion(Location $location) : Zombie{
		return new Drowned($location);
	}

	private function convertInWater() : void{
		$converted = $this->createWaterConversion(Location::fromObject($this->location, $this->getWorld()));
		$converted->setMaxHealth($this->getMaxHealth());
		$converted->setHealth($this->getHealth());
		if($this->getNameTag() !== ""){
			$converted->setNameTag($this->getNameTag()); //a named zombie keeps its name through the conversion
		}
		$converted->spawnToAll();
		$this->flagForDespawn();
	}

	public function getDrops() : array{
		$drops = [
			VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(0, 2))
		];

		if(mt_rand(0, 199) < 5){
			switch(mt_rand(0, 2)){
				case 0:
					$drops[] = VanillaItems::IRON_INGOT();
					break;
				case 1:
					$drops[] = VanillaItems::CARROT();
					break;
				case 2:
					$drops[] = VanillaItems::POTATO();
					break;
			}
		}

		return $drops;
	}

	public function getXpDropAmount() : int{
		//TODO: check for equipment and whether it's a baby
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::ZOMBIE_SPAWN_EGG();
	}
}
