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

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function cos;
use function deg2rad;
use function sin;

/**
 * Base for entities a player can sit in and steer (boats...). Mounting seats the player via entity-link + the rider
 * metadata the Bedrock client reads to take local control; the controlling player's input is fed back in through the
 * subclass's vehicle-input handler. Ported from the vehicle support in Root-Studios/RootMine-MP.
 */
abstract class Rideable extends Entity{

	protected ?Player $rider = null;
	protected ?Player $passenger = null;

	/** Where the steering rider sits, relative to the vehicle. */
	abstract protected function getRiderSeatPosition() : Vector3;

	protected function canHavePassenger() : bool{
		return true;
	}

	protected function getPassengerSeatPosition() : Vector3{
		return new Vector3(-0.6, 1.02, 0.0);
	}

	protected function getDismountPosition(Player $player) : Vector3{
		$yaw = deg2rad($this->location->yaw);
		return $this->location->asVector3()->add(cos($yaw) * 0.9, 0.25, sin($yaw) * 0.9);
	}

	public function getRider() : ?Player{
		return $this->rider;
	}

	public function setRider(?Player $player) : void{
		if($this->rider === $player){
			return;
		}
		$this->dismountRider(false, false);
		$this->rider = $player;
		if($player !== null){
			$this->mountPlayer($player, $this->getRiderSeatPosition());
			$this->setMotion(new Vector3(0.0, 0.0, 0.0));
			$this->keepMovement = true;
			$this->broadcastLink($player, EntityLink::TYPE_RIDER, true, true);
		}
	}

	public function dismountRider(bool $promotePassenger = true, bool $syncPosition = true) : void{
		if($this->rider === null){
			return;
		}
		$rider = $this->rider;
		$this->rider = null;
		$this->unmountPlayer($rider);
		$this->broadcastLink($rider, EntityLink::TYPE_REMOVE, true, true);
		if($syncPosition){
			$this->syncDismountedPlayer($rider);
		}

		if($promotePassenger && $this->passenger !== null){
			$passenger = $this->passenger;
			$this->dismountPassenger(false);
			$this->setRider($passenger);
		}else{
			$this->keepMovement = false;
		}
	}

	public function getPassenger() : ?Player{
		return $this->passenger;
	}

	public function setPassenger(?Player $player) : void{
		if(!$this->canHavePassenger()){
			throw new \BadFunctionCallException("This entity can't have a passenger");
		}
		if($this->passenger === $player){
			return;
		}
		$this->dismountPassenger(false);
		$this->passenger = $player;
		if($player !== null){
			$this->mountPlayer($player, $this->getPassengerSeatPosition());
			$this->broadcastLink($player, EntityLink::TYPE_PASSENGER, true, false);
		}
	}

	public function dismountPassenger(bool $syncPosition = true) : void{
		if($this->passenger === null){
			return;
		}
		$passenger = $this->passenger;
		$this->passenger = null;
		$this->unmountPlayer($passenger);
		$this->broadcastLink($passenger, EntityLink::TYPE_REMOVE, true, false);
		if($syncPosition){
			$this->syncDismountedPlayer($passenger);
		}
	}

	protected function mountPlayer(Player $player, Vector3 $seatPosition) : void{
		$playerProps = $player->getNetworkProperties();
		$playerProps->setGenericFlag(EntityMetadataFlags::RIDING, true);
		$playerProps->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, $seatPosition);
		$playerProps->setByte(EntityMetadataProperties::RIDER_ROTATION_LOCKED, 1);
		$playerProps->setFloat(EntityMetadataProperties::RIDER_MIN_ROTATION, 0.0);
		$playerProps->setFloat(EntityMetadataProperties::RIDER_MAX_ROTATION, 90.0);
		$playerProps->setFloat(EntityMetadataProperties::RIDER_SEAT_ROTATION_OFFSET, -90.0);
		$player->sendData(null);
	}

	protected function unmountPlayer(Player $player) : void{
		$playerProps = $player->getNetworkProperties();
		$playerProps->setGenericFlag(EntityMetadataFlags::RIDING, false);
		$playerProps->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, new Vector3(0.0, 0.0, 0.0));
		$playerProps->setByte(EntityMetadataProperties::RIDER_ROTATION_LOCKED, 0);
		$player->sendData(null);
	}

	protected function broadcastLink(Player $player, int $type, bool $immediate, bool $riderInitiated) : void{
		NetworkBroadcastUtils::broadcastPackets($this->hasSpawned, [SetActorLinkPacket::create(new EntityLink(
			$this->getId(),
			$player->getId(),
			$type,
			$immediate,
			$riderInitiated,
			0.0
		))]);
	}

	protected function syncDismountedPlayer(Player $player) : void{
		if(!$player->isClosed() && $player->getWorld() === $this->getWorld()){
			$player->handleMovement($this->getDismountPosition($player));
		}
	}

	/**
	 * Dismounts the given player from whatever nearby rideable they are riding.
	 */
	public static function dismountFrom(Player $player, bool $syncPosition = true) : void{
		foreach($player->getWorld()->getNearbyEntities($player->getBoundingBox()->expandedCopy(4.0, 4.0, 4.0)) as $entity){
			if($entity instanceof self){
				if($entity->getRider() === $player){
					$entity->dismountRider(syncPosition: $syncPosition);
				}elseif($entity->getPassenger() === $player){
					$entity->dismountPassenger($syncPosition);
				}
			}
		}
	}
}
