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

use pocketmine\player\Player;

/**
 * Base for stand-alone vehicles a player can sit in and steer (boats...). Mounting seats the player via entity-link plus
 * the rider metadata the Bedrock client reads to take local control; the controlling player's input is fed back in
 * through the subclass's vehicle-input handler. The reusable seat/mount plumbing lives in {@link RidingTrait}, shared
 * with steerable mobs like the {@link Strider}. Ported from the vehicle support in Root-Studios/RootMine-MP.
 */
abstract class Rideable extends Entity implements RideableEntity{
	use RidingTrait;

	/**
	 * Dismounts the given player from whatever nearby rideable (vehicle or steerable mob) they are riding.
	 */
	public static function dismountFrom(Player $player, bool $syncPosition = true) : void{
		foreach($player->getWorld()->getNearbyEntities($player->getBoundingBox()->expandedCopy(4.0, 4.0, 4.0)) as $entity){
			if($entity instanceof RideableEntity){
				if($entity->getRider() === $player){
					$entity->dismountRider(syncPosition: $syncPosition);
				}elseif($entity->getPassenger() === $player){
					$entity->dismountPassenger($syncPosition);
				}
			}
		}
	}
}
