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

use pocketmine\entity\ai\goal\WolfAttackGoal;
use pocketmine\entity\ai\sensor\DefendOwnerSensor;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use function in_array;
use function mt_rand;

/**
 * Tameable wolf. Tame a wild one with a bone (1-in-3 chance per bone), feed it meat to heal it, and right-click your own
 * wolf to make it sit/stand. A standing tamed wolf trots after its owner, teleports to them when left too far behind, and
 * fights back / defends its owner (see {@link WolfAttackGoal}).
 */
class Wolf extends TameableAnimal{

	/** Meat (and rotten flesh) a tamed wolf will eat to heal. */
	private const FOOD_IDS = [
		ItemTypeIds::RAW_BEEF, ItemTypeIds::RAW_CHICKEN, ItemTypeIds::RAW_MUTTON,
		ItemTypeIds::RAW_PORKCHOP, ItemTypeIds::STEAK, ItemTypeIds::ROTTEN_FLESH,
	];
	private const HEAL_PER_FEED = 4;

	public static function getNetworkTypeId() : string{ return EntityIds::WOLF; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo($this->isBaby() ? 0.4 : 0.85, $this->isBaby() ? 0.3 : 0.6);
	}

	protected function getDefaultMaxHealth() : int{
		return $this->tamed ? 20 : 8;
	}

	public function getName() : string{
		return "Wolf";
	}

	protected function tryTameWith(Player $player, Item $item) : bool{
		if($item->getTypeId() === ItemTypeIds::BONE){
			$this->attemptTame($player, $item, 3);
			return true;
		}
		return false;
	}

	protected function tryFeedTamed(Player $player, Item $item) : bool{
		if(in_array($item->getTypeId(), self::FOOD_IDS, true)){
			$this->feedHealOrBreed($player, $item, self::HEAL_PER_FEED);
			return true;
		}
		return false;
	}

	protected function registerExtraGoals() : void{
		parent::registerExtraGoals();
		$this->addSensor(new DefendOwnerSensor());
		//highest priority: fighting back / defending the owner preempts panicking, following and wandering
		$this->addGoal(0, new WolfAttackGoal());
	}

	protected function onTamed() : void{
		$this->refreshAttackDamage();
	}

	private function refreshAttackDamage() : void{
		//vanilla bite damage: 4 once tamed, 3 while wild; a pup is harmless until it grows up
		$this->getAttributeMap()->get(Attribute::ATTACK_DAMAGE)?->setValue($this->isBaby() ? 0.0 : ($this->tamed ? 4.0 : 3.0));
	}

	public function setBaby(bool $baby = true) : void{
		parent::setBaby($baby);
		//re-apply bite damage whenever the age flips (bred pup, grow-up) so a baby never deals adult damage
		$this->refreshAttackDamage();
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->refreshAttackDamage();
	}

	public function getDrops() : array{
		return [];
	}

	public function getXpDropAmount() : int{
		return $this->isBaby() ? 0 : mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::WOLF_SPAWN_EGG();
	}
}
