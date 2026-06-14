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
use pocketmine\entity\Attribute;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Rideable;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\entity\Attribute as NetworkAttribute;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\player\Player;
use pocketmine\world\sound\BlockBreakSound;
use function array_filter;
use function array_map;
use function array_values;

class Boat extends Rideable{

	private const TAG_WOOD_TYPE = "PMMPBoatWoodType";
	/**
	 * Vertical speed the hull is FORCED to while its base block is water. A direct override (not an additive nudge) so it
	 * always beats whatever downward speed gravity built up - the collision-less ridden hull would otherwise reach
	 * terminal velocity and sink straight through the water, which a gentle additive push could never reverse.
	 */
	private const FLOAT_RISE = 0.1;

	protected int $woodType = 0;

	public static function getNetworkTypeId() : string{ return EntityIds::BOAT; }

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.455, 1.4); }

	protected function getInitialDragMultiplier() : float{ return 0.1; }

	protected function getInitialGravity() : float{ return 0.04; }

	protected function getRiderSeatPosition() : Vector3{
		return new Vector3(0.2, 1.02, 0.0);
	}

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

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($this->rider === null){
			Rideable::dismountFrom($player, false);
			$this->setRider($player);
			return true;
		}
		if($this->rider === $player || $this->passenger === $player){
			return true;
		}
		if($this->passenger === null){
			Rideable::dismountFrom($player, false);
			$this->setPassenger($player);
			return true;
		}
		return false;
	}

	/**
	 * Steers the boat from its rider's authoritative input: the client drives the boat locally (so it floats correctly
	 * and never sinks), and reports its position through the rider's movement, which we copy onto the server-side boat so
	 * other players, collision and dismounting track it. Called by the network handler for the controlling player.
	 */
	public function handleVehicleInput(Player $player, PlayerAuthInputPacket $packet) : bool{
		if($this->rider !== $player){
			return false;
		}
		$vehicleInfo = $packet->getVehicleInfo();
		if($vehicleInfo !== null && $vehicleInfo->getPredictedVehicleActorUniqueId() !== $this->getId()){
			return false;
		}

		//steer in the horizontal plane only: copy the rider's reported X/Z, but leave Y to buoyancy (entityBaseTick) so the
		//hull stays pinned to the waterline. Feeding the rider's eye-height Y back in here is what dragged the boat under.
		$reported = $packet->getPosition();
		$target = new Vector3($reported->x, $this->location->y, $reported->z);
		if($target->distanceSquared($this->location->asVector3()) <= 100.0){
			$this->setPositionAndRotation($target, $packet->getYaw(), 0.0);
			$this->updateMovement();
		}
		return true;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->closed || !$this->isAlive()){
			return $hasUpdate;
		}

		if($this->rider !== null && ($this->rider->isClosed() || !$this->rider->isAlive() || $this->rider->getWorld() !== $this->getWorld())){
			$this->dismountRider();
		}
		if($this->passenger !== null && ($this->passenger->isClosed() || !$this->passenger->isAlive() || $this->passenger->getWorld() !== $this->getWorld())){
			$this->dismountPassenger();
		}

		//buoyancy, applied whether or not someone is riding: while the hull's own block is water, FORCE an upward speed so
		//it rises to and bobs at the surface. Direct (not additive) so it reverses any gravity-built sink - this is what
		//keeps a ridden, collision-less boat from sinking straight through the water.
		$pos = $this->location;
		if($this->getWorld()->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ()) instanceof Liquid){
			$this->motion = $this->motion->withComponents($this->motion->x * 0.9, self::FLOAT_RISE, $this->motion->z * 0.9);
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
		$this->dismountRider();
		$this->dismountPassenger();
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

	protected function sendSpawnPacket(Player $player) : void{
		$player->getNetworkSession()->sendDataPacket(AddActorPacket::create(
			$this->getId(),
			$this->getId(),
			static::getNetworkTypeId(),
			$this->getOffsetPosition($this->location->asVector3()),
			$this->getMotion(),
			$this->location->pitch,
			$this->location->yaw,
			$this->location->yaw,
			$this->location->yaw,
			array_map(function(Attribute $attr) : NetworkAttribute{
				return new NetworkAttribute($attr->getId(), $attr->getMinValue(), $attr->getMaxValue(), $attr->getValue(), $attr->getDefaultValue(), []);
			}, $this->attributeMap->getAll()),
			$this->getAllNetworkData(),
			new PropertySyncData([], []),
			array_values(array_filter([
				$this->rider !== null ? new EntityLink($this->getId(), $this->rider->getId(), EntityLink::TYPE_RIDER, true, true, 0.0) : null,
				$this->passenger !== null ? new EntityLink($this->getId(), $this->passenger->getId(), EntityLink::TYPE_PASSENGER, true, false, 0.0) : null,
			]))
		));
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
