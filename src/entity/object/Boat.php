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

namespace pocketmine\entity\object;

use pocketmine\block\Liquid;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\sound\BlockBreakSound;

class Boat extends Entity{

	private const TAG_WOOD_TYPE = "PMMPBoatWoodType";
	private const BUOYANCY = 0.023;

	protected int $woodType = 0;

	public static function getNetworkTypeId() : string{ return EntityIds::BOAT; }

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.455, 1.4); }

	protected function getInitialDragMultiplier() : float{ return 0.05; }

	protected function getInitialGravity() : float{ return 0.04; }

	public function getName() : string{
		return "Boat";
	}

	public function getWoodType() : int{
		return $this->woodType;
	}

	public function setWoodType(int $woodType) : void{
		$this->woodType = $woodType;
		$this->networkPropertiesDirty = true;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->closed || !$this->isAlive()){
			return $hasUpdate;
		}

		$pos = $this->location;
		$block = $this->getWorld()->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		if($block instanceof Liquid){
			//apply buoyancy so the boat rises to and rests on the surface instead of sinking
			$this->motion = $this->motion->withComponents(
				$this->motion->x * 0.9,
				$this->motion->y < 0 ? $this->motion->y * 0.5 + self::BUOYANCY : self::BUOYANCY,
				$this->motion->z * 0.9
			);
			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if($source->isCancelled()){
			return;
		}
		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager instanceof Player && $damager->isCreative()){
				$this->destroyBoat(false);
				return;
			}
		}
		if($this->getHealth() <= 0){
			$this->destroyBoat(true);
		}
	}

	private function destroyBoat(bool $drop) : void{
		if($drop){
			$this->getWorld()->dropItem($this->location, $this->getBoatItem());
		}
		$this->getWorld()->addSound($this->location, new BlockBreakSound(VanillaBlocks::OAK_PLANKS()));
		$this->flagForDespawn();
	}

	private function getBoatItem() : Item{
		return VanillaItems::OAK_BOAT();
	}

	public function getPickedItem() : ?Item{
		return $this->getBoatItem();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->woodType);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_WOOD_TYPE, $this->woodType);
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->woodType = $nbt->getInt(self::TAG_WOOD_TYPE, 0);
	}
}
