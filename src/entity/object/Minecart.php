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

use pocketmine\entity\Rideable;
use pocketmine\entity\RideableEntity;
use pocketmine\entity\RidingTrait;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;

class Minecart extends AbstractMinecart implements RideableEntity{
	use RidingTrait;

	public static function getNetworkTypeId() : string{ return EntityIds::MINECART; }

	public function getName() : string{
		return "Minecart";
	}

	protected function getMinecartItem() : Item{
		return VanillaItems::MINECART();
	}

	protected function getRiderSeatPosition() : Vector3{
		//the rider sits in the cart; this is the camera offset from the cart's base. 0.6 rendered the view down inside the
		//cart, so seat it near a normal seated eye height. Tune in-game if it sits too high/low.
		return new Vector3(0.0, 1.4, 0.0);
	}

	protected function canHavePassenger() : bool{
		return false; //a minecart carries a single rider
	}

	protected function lockRiderRotation() : bool{
		return false; //look around freely while riding, not locked like a boat seat
	}

	protected function keepMovementWhileRidden() : bool{
		return false; //server-authoritative: the cart's own rail physics drive it; the rider just follows along
	}

	public function getRiderTrackingPosition(Vector3 $clientPredicted) : Vector3{
		//track the rider to the cart's own server-moved position (the client doesn't predict the cart rolling along rails),
		//so the player follows the cart instead of staying at the boarding point
		return $this->location->asVector3();
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($this->rider === null){
			Rideable::dismountFrom($player, false);
			$this->setRider($player);
			return true;
		}
		return false;
	}

	public function handleVehicleInput(Player $player, PlayerAuthInputPacket $packet) : bool{
		return $this->rider === $player; //the cart isn't steered - just confirm the rider so the handler keeps tracking them
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		//a rider who logged out, died or changed world can't keep riding
		if($this->rider !== null && ($this->rider->isClosed() || !$this->rider->isAlive() || $this->rider->getWorld() !== $this->getWorld())){
			$this->dismountRider();
		}
		return $hasUpdate;
	}

	protected function destroyCart(bool $dropItems) : void{
		$this->dismountRider();
		parent::destroyCart($dropItems);
	}

	protected function onDispose() : void{
		$this->disposeRiding(); //chunk unload / world shutdown / direct close bypasses destroyCart - free the rider here too
		parent::onDispose();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		//mark the driving seat so the client reports the rider's input (incl. the sneak that dismounts). We do NOT set
		//WASD_CONTROLLED (the cart rolls on rails server-side, the client must not predict-and-fight it) and we do NOT set
		//DOES_SERVER_AUTH_ONLY_DISMOUNT here: on a minecart that flag blocked the sneak entirely (could not dismount),
		//unlike on the strider mob - so we let the client dismount natively and catch the sneak in the input handler.
		$properties->setByte(EntityMetadataProperties::CONTROLLING_RIDER_SEAT_NUMBER, 0);
	}
}
