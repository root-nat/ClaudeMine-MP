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
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use function mt_rand;

class Slime extends Monster{

	protected const TAG_SIZE = "Size"; //TAG_Int

	protected int $slimeSize = SlimeSizeLogic::SIZE_LARGE;

	public static function getNetworkTypeId() : string{ return EntityIds::SLIME; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		$edge = SlimeSizeLogic::hitboxEdge($this->slimeSize);
		return new EntitySizeInfo($edge, $edge);
	}

	protected function getDefaultMaxHealth() : int{
		return SlimeSizeLogic::healthFor($this->slimeSize);
	}

	public function getName() : string{
		return "Slime";
	}

	public function getSlimeSize() : int{
		return $this->slimeSize;
	}

	public function setSlimeSize(int $size) : void{
		$this->slimeSize = SlimeSizeLogic::clampSize($size);
		$this->setMaxHealth(SlimeSizeLogic::healthFor($this->slimeSize));
		$this->setHealth($this->getMaxHealth());
		$this->setSize($this->getInitialSizeInfo());
		$this->networkPropertiesDirty = true;
	}

	protected function registerAttackGoals() : void{
		$this->addGoal(2, new MeleeAttackGoal());
	}

	protected function getTouchDamage() : int{
		return SlimeSizeLogic::slimeDamage($this->slimeSize);
	}

	protected function getChildEntity(Location $location) : Slime{
		return new Slime($location);
	}

	protected function onDeath() : void{
		parent::onDeath();
		if(!SlimeSizeLogic::canSplit($this->slimeSize)){
			return;
		}
		$childSize = SlimeSizeLogic::childSize($this->slimeSize);
		$count = SlimeSizeLogic::splitCount(mt_rand(0, 2));
		$world = $this->getWorld();
		for($i = 0; $i < $count; ++$i){
			$offsetX = (mt_rand(-100, 100) / 100) * 0.5;
			$offsetZ = (mt_rand(-100, 100) / 100) * 0.5;
			$child = $this->getChildEntity(Location::fromObject(
				$this->location->add($offsetX, 0.5, $offsetZ),
				$world,
				mt_rand(0, 359),
				0
			));
			$child->setSlimeSize($childSize);
			$child->spawnToAll();
		}
	}

	public function getXpDropAmount() : int{
		return SlimeSizeLogic::xpFor($this->slimeSize);
	}

	public function getDrops() : array{
		//only the smallest slimes drop slimeballs
		return SlimeSizeLogic::canSplit($this->slimeSize) ? [] : [VanillaItems::SLIMEBALL()->setCount(mt_rand(0, 2))];
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::SLIME_SPAWN_EGG();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->slimeSize);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_SIZE, $this->slimeSize);
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->slimeSize = SlimeSizeLogic::clampSize($nbt->getInt(self::TAG_SIZE, SlimeSizeLogic::SIZE_LARGE));
		parent::initEntity($nbt);
		//the Entity constructor sized us before the real size was loaded; correct the hitbox now
		$this->setSize($this->getInitialSizeInfo());
	}
}
