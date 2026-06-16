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
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\player\Player;

/**
 * An entity a player can sit on and steer - a boat, or a saddled, controllable mob like a {@link Strider}. The common
 * mount/dismount/seat plumbing lives in {@link RidingTrait}; this interface is what the network input router and the
 * dismount helper match against, so a steerable mob (not just a {@link Rideable} vehicle) is recognised the same way.
 */
interface RideableEntity{

	public function getRider() : ?Player;

	public function getPassenger() : ?Player;

	public function setRider(?Player $player) : void;

	public function dismountRider(bool $promotePassenger = true, bool $syncPosition = true) : void;

	public function dismountPassenger(bool $syncPosition = true) : void;

	/**
	 * Feeds the controlling rider's authoritative input to this vehicle for the current tick. Returns true if the input
	 * was consumed (the player is its current rider).
	 */
	public function handleVehicleInput(Player $player, PlayerAuthInputPacket $packet) : bool;

	/**
	 * Where the controlling rider's body should be tracked to on the server this tick, given the client-reported position.
	 * Boats use the client position (client-predicted); a server-authoritative mob returns its own moved position.
	 */
	public function getRiderTrackingPosition(Vector3 $clientPredicted) : Vector3;
}
