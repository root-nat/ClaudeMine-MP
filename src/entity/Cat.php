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
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function in_array;
use function mt_rand;

/**
 * Tameable cat. Tame a wild one with raw cod or salmon (1-in-3 chance per fish), feed it the same fish to heal or breed
 * it, and right-click your own cat to make it sit/stand. A standing tamed cat follows its owner and teleports to them when
 * left too far behind. Each cat spawns with one of the vanilla coat variants, which kittens inherit from a parent.
 */
class Cat extends TameableAnimal{

	private const TAG_VARIANT = "Variant"; //TAG_Int

	/** Raw cod and salmon both tame and feed a cat. */
	private const FOOD_IDS = [ItemTypeIds::RAW_FISH, ItemTypeIds::RAW_SALMON];
	private const HEAL_PER_FEED = 2;
	/** Number of vanilla coat variants (tabby, tuxedo, red, siamese, british, calico, persian, ragdoll, white, jellie, black). */
	private const VARIANT_COUNT = 11;

	protected int $variant = 0;

	public static function getNetworkTypeId() : string{ return EntityIds::CAT; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo($this->isBaby() ? 0.35 : 0.7, $this->isBaby() ? 0.3 : 0.6);
	}

	protected function getDefaultMaxHealth() : int{
		return 10;
	}

	public function getName() : string{
		return "Cat";
	}

	public function getVariant() : int{ return $this->variant; }

	public function setVariant(int $variant) : void{
		if($this->variant !== $variant){
			$this->variant = $variant;
			$this->networkPropertiesDirty = true;
		}
	}

	protected function tryTameWith(Player $player, Item $item) : bool{
		if(in_array($item->getTypeId(), self::FOOD_IDS, true)){
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

	protected function createChild() : ?Animal{
		$baby = parent::createChild();
		if($baby instanceof Cat){
			//a kitten takes after this parent's coat
			$baby->setVariant($this->variant);
		}
		return $baby;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->variant);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_VARIANT, $this->variant);
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->variant = $nbt->getInt(self::TAG_VARIANT, mt_rand(0, self::VARIANT_COUNT - 1));
		parent::initEntity($nbt);
	}

	public function getDrops() : array{
		return [];
	}

	public function getXpDropAmount() : int{
		return $this->isBaby() ? 0 : mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::CAT_SPAWN_EGG();
	}
}
