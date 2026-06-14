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
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;

class Chicken extends Animal{

	private const TAG_EGG_TIME = "EggLayTime"; //TAG_Int
	/** Vanilla lays an egg somewhere in this window (5-10 minutes). */
	private const EGG_LAY_MIN_TICKS = 6000;
	private const EGG_LAY_MAX_TICKS = 12000;

	private int $eggLayTime = 0;

	public static function getNetworkTypeId() : string{ return EntityIds::CHICKEN; }

	protected function getBreedingSpecies() : ?string{ return BreedingHelper::CHICKEN; }

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

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->closed || !$this->isAlive()){
			return $hasUpdate;
		}

		//grown chickens periodically lay an egg on the ground
		if(!$this->isBaby()){
			$this->eggLayTime -= $tickDiff;
			if($this->eggLayTime <= 0){
				$this->getWorld()->dropItem($this->location, VanillaItems::EGG());
				$this->eggLayTime = mt_rand(self::EGG_LAY_MIN_TICKS, self::EGG_LAY_MAX_TICKS);
				$hasUpdate = true;
			}
		}

		return $hasUpdate;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_EGG_TIME, $this->eggLayTime);
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->eggLayTime = $nbt->getInt(self::TAG_EGG_TIME, mt_rand(self::EGG_LAY_MIN_TICKS, self::EGG_LAY_MAX_TICKS));
		parent::initEntity($nbt);
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
