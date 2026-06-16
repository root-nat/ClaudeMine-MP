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
 * The shared seat/mount plumbing behind anything a player can ride and steer: it seats the player via entity-link plus
 * the rider metadata the Bedrock client reads to take local control, and tears that down on dismount. Used by the
 * {@link Rideable} vehicle base (boats) and by steerable mobs such as the {@link Strider}. The host class only needs to
 * be an {@link Entity} (for location/motion/viewers) and to provide getRiderSeatPosition(). Ported from the vehicle
 * support in Root-Studios/RootMine-MP.
 *
 * @phpstan-require-implements RideableEntity
 */
trait RidingTrait{

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

	/**
	 * Whether to drop server-side collision while a rider is steering (the client predicts the ride locally). True for a
	 * boat, whose hull would otherwise stick on shore blocks the predicting client drives straight over; a walking mob
	 * keeps its collision so it can't be steered through walls.
	 */
	protected function keepMovementWhileRidden() : bool{
		return true;
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
			if($this->keepMovementWhileRidden()){
				//the vehicle is steered from the rider's input and the client predicts it locally (WASD_CONTROLLED); move it
				//without block collision so the server hull never sticks on shore/edge blocks the predicting client drives
				//straight over - that mismatch is what rubber-bands the steering
				$this->keepMovement = true;
			}
			$this->broadcastLink($player, EntityLink::TYPE_RIDER, true, true);
			$player->setRidingVehicle($this);
		}
	}

	/**
	 * Where the controlling rider's body is tracked to on the server this tick, given the position the client reported.
	 * A client-predicted vehicle (boat) uses the client position; a server-authoritative mob (strider) overrides this to
	 * track the rider to its own server-moved position so the player stays on it as the server steers it.
	 */
	public function getRiderTrackingPosition(Vector3 $clientPredicted) : Vector3{
		return $clientPredicted;
	}

	public function dismountRider(bool $promotePassenger = true, bool $syncPosition = true) : void{
		if($this->rider === null){
			return;
		}
		$rider = $this->rider;
		$this->rider = null;
		$rider->setRidingVehicle(null);
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
			//no one left steering: restore normal collision so an idle/empty vehicle rests on terrain again
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

	/**
	 * Tears down any rider/passenger when the vehicle itself is disposed (chunk unload, world shutdown, a direct close) -
	 * paths that bypass the in-world destroy/dismount and would otherwise leave a player permanently linked to a now-dead
	 * vehicle (stuck riding, unable to dismount). Hosts must call this from their onDispose(); the teleport is skipped
	 * (syncPosition false) since we're tearing down.
	 */
	protected function disposeRiding() : void{
		if($this->rider !== null){
			$this->dismountRider(syncPosition: false);
		}
		if($this->passenger !== null){
			$this->dismountPassenger(false);
		}
	}

	protected function mountPlayer(Player $player, Vector3 $seatPosition) : void{
		$playerProps = $player->getNetworkProperties();
		$playerProps->setGenericFlag(EntityMetadataFlags::RIDING, true);
		//the seat must be sent: the client has no built-in rider seat for a server-spawned vehicle/mob, so RIDER_SEAT_POSITION
		//is the camera/rider offset. A tall mob therefore needs a seat ABOVE its body, or the camera renders inside it
		$playerProps->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, $seatPosition);
		if($this->lockRiderRotation()){
			//a boat locks the rider's view along the hull (the -90° offset aims them down the boat's length)
			$playerProps->setByte(EntityMetadataProperties::RIDER_ROTATION_LOCKED, 1);
			$playerProps->setFloat(EntityMetadataProperties::RIDER_MIN_ROTATION, 0.0);
			$playerProps->setFloat(EntityMetadataProperties::RIDER_MAX_ROTATION, 90.0);
			$playerProps->setFloat(EntityMetadataProperties::RIDER_SEAT_ROTATION_OFFSET, -90.0);
		}else{
			//a mob a player sits astride (strider) looks around freely; the boat lock + -90° offset would swing the camera away
			$playerProps->setByte(EntityMetadataProperties::RIDER_ROTATION_LOCKED, 0);
			$playerProps->setFloat(EntityMetadataProperties::RIDER_SEAT_ROTATION_OFFSET, 0.0);
		}
		$player->sendData(null);
	}

	/**
	 * Whether the rider's view is locked to face along the vehicle - true for a boat. A mob a player sits astride (the
	 * strider) returns false so they can look around freely.
	 */
	protected function lockRiderRotation() : bool{
		return true;
	}

	protected function unmountPlayer(Player $player) : void{
		$playerProps = $player->getNetworkProperties();
		$playerProps->setGenericFlag(EntityMetadataFlags::RIDING, false);
		$playerProps->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, new Vector3(0.0, 0.0, 0.0));
		$playerProps->setByte(EntityMetadataProperties::RIDER_ROTATION_LOCKED, 0);
		$playerProps->setFloat(EntityMetadataProperties::RIDER_SEAT_ROTATION_OFFSET, 0.0);
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
			//teleport (not handleMovement): a plain server-side move sends the dismounting player no authoritative position
			//packet, so the Bedrock client is never told it left the seat and keeps refusing fresh interact transactions
			//against the vehicle - that is the "can't remount after dismounting" bug. MODE_TELEPORT authoritatively unseats it.
			$player->teleport($this->getDismountPosition($player));
		}
	}
}
