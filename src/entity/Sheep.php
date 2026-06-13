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

use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use function mt_rand;

class Sheep extends Animal{

	private const TAG_SHEARED = "Sheared"; //TAG_Byte
	private const TAG_COLOR = "Color"; //TAG_Byte

	protected bool $sheared = false;
	protected int $color = 0; //DyeColor white

	public static function getNetworkTypeId() : string{ return EntityIds::SHEEP; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo($this->isBaby() ? 0.65 : 1.3, $this->isBaby() ? 0.45 : 0.9);
	}

	protected function getDefaultMaxHealth() : int{
		return 8;
	}

	public function getName() : string{
		return "Sheep";
	}

	public function isSheared() : bool{
		return $this->sheared;
	}

	public function setSheared(bool $sheared = true) : void{
		$this->sheared = $sheared;
		$this->networkPropertiesDirty = true;
	}

	public function getColor() : int{
		return $this->color;
	}

	public function setColor(int $color) : void{
		$this->color = $color;
		$this->networkPropertiesDirty = true;
	}

	public function getDrops() : array{
		if($this->sheared){
			return [];
		}
		//TODO: drop wool matching the stored colour once dye-colour mapping is wired through
		return [VanillaBlocks::WOOL()->asItem()];
	}

	public function getXpDropAmount() : int{
		return $this->isBaby() ? 0 : mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::SHEEP_SPAWN_EGG();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::SHEARED, $this->sheared);
		$properties->setByte(EntityMetadataProperties::COLOR, $this->color);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_SHEARED, $this->sheared ? 1 : 0);
		$nbt->setByte(self::TAG_COLOR, $this->color);
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->sheared = $nbt->getByte(self::TAG_SHEARED, 0) !== 0;
		$this->color = $nbt->getByte(self::TAG_COLOR, 0);
		parent::initEntity($nbt);
	}
}
