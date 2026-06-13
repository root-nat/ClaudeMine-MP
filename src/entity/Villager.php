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

use pocketmine\entity\villager\GossipContainer;
use pocketmine\entity\villager\GossipType;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;

class Villager extends Living implements Ageable{
	public const PROFESSION_FARMER = 0;
	public const PROFESSION_LIBRARIAN = 1;
	public const PROFESSION_PRIEST = 2;
	public const PROFESSION_BLACKSMITH = 3;
	public const PROFESSION_BUTCHER = 4;

	private const TAG_PROFESSION = "Profession"; //TAG_Int

	public static function getNetworkTypeId() : string{ return EntityIds::VILLAGER; }

	private bool $baby = false;
	private int $profession = self::PROFESSION_FARMER;
	private GossipContainer $gossip;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6); //TODO: eye height??
	}

	public function getName() : string{
		return "Villager";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->gossip = new GossipContainer();

		/** @var int $profession */
		$profession = $nbt->getInt(self::TAG_PROFESSION, self::PROFESSION_FARMER);

		if($profession > 4 || $profession < 0){
			$profession = self::PROFESSION_FARMER;
		}

		$this->setProfession($profession);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_PROFESSION, $this->getProfession());

		return $nbt;
	}

	/**
	 * Sets the villager profession
	 */
	public function setProfession(int $profession) : void{
		$this->profession = $profession; //TODO: validation
		$this->networkPropertiesDirty = true;
	}

	public function getProfession() : int{
		return $this->profession;
	}

	/**
	 * Returns this villager's gossip memory, which backs player reputation. Note: gossip is not yet persisted to NBT.
	 */
	public function getGossip() : GossipContainer{
		return $this->gossip;
	}

	/**
	 * Records a gossip event about a player (e.g. trading, attacking, or curing this villager).
	 */
	public function recordGossip(string $playerKey, GossipType $type, int $amount) : void{
		$this->gossip->add($playerKey, $type, $amount);
	}

	/**
	 * Returns the given player's reputation with this villager (positive = liked, negative = disliked).
	 */
	public function getReputation(string $playerKey) : int{
		return $this->gossip->getReputation($playerKey);
	}

	public function isBaby() : bool{
		return $this->baby;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::VILLAGER_SPAWN_EGG();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->baby);

		$properties->setInt(EntityMetadataProperties::VARIANT, $this->profession);
	}
}
