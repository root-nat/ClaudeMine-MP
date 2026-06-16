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

use pocketmine\block\Lava;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\item\WarpedFungusOnAStick;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function abs;
use function cos;
use function deg2rad;
use function floor;
use function max;
use function min;
use function mt_rand;
use function sin;
use const INF;

/**
 * A warm-blooded strider of the lava seas: it strides across lava as if it were solid ground instead of sinking. Drag
 * it onto land or into water and it shivers and slows, and water actively chills and hurts it. Passive, fully fire- and
 * lava-proof, and bred with warped fungus. Once saddled it can be ridden and steered with a warped fungus on a stick.
 * (The baby/zombified-piglin jockey and the slower cold gait on land are not modelled yet.)
 */
class Strider extends Animal implements RideableEntity{
	use RidingTrait;

	/** Reference height above the strider's feet used to measure distance to the lava surface. MUST stay non-zero: it sets
	 * the resting equilibrium to feet = surface - OFFSET, keeping the bounding box's floor one cube below the surface so
	 * the lava block never drops out of getBlocksAroundWithEntityInsideActions at rest (a zero offset rests feet exactly on
	 * the block boundary, the lava leaves the sample, getLavaLevel goes INF, gravity kicks in and the strider jitters). It
	 * also makes the strider wade legs-deep in the lava like vanilla. Reuses the boat's verified value. */
	private const LAVA_BASE_OFFSET = 0.375;
	/** Lava-surface band and settle speeds for the strider's buoyancy (ported from the boat's water model). */
	private const SINKING_DEPTH = 0.07;
	private const SINKING_SPEED = 0.0005;
	private const SINKING_MAX_SPEED = 0.005;

	/** Steering input must deviate this far from neutral before it moves the strider (analog stick deadzone). */
	private const INPUT_DEADZONE = 0.01;
	/** Per-tick steering speed, and the faster speed while a warped-fungus boost is active. */
	private const STEER_SPEED = 0.15;
	private const BOOST_SPEED = 0.3;
	/** How long a warped-fungus-on-a-stick boost lasts (2s). */
	private const BOOST_TICKS = 40;

	private const TAG_SADDLED = "Saddled"; //TAG_Byte

	/** Hysteresis flag so the lava settle doesn't oscillate across the surface band. */
	private bool $sinking = false;
	private bool $saddled = false;
	private int $boostTicks = 0;

	public static function getNetworkTypeId() : string{ return EntityIds::STRIDER; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return $this->isBaby() ? new EntitySizeInfo(0.85, 0.45) : new EntitySizeInfo(1.7, 0.9);
	}

	protected function getDefaultMaxHealth() : int{
		return 20;
	}

	public function getName() : string{
		return "Strider";
	}

	public function isFireProof() : bool{
		return true; //a creature of the lava seas - fire and lava cannot touch it
	}

	protected function getBreedingSpecies() : ?string{
		return BreedingHelper::STRIDER; //tempted and bred with warped fungus
	}

	protected function getRiderSeatPosition() : Vector3{
		//seat the camera ABOVE the ~1.7-tall body: with WASD_CONTROLLED the seat vector IS the rider/camera offset, so a
		//Y inside the body height renders the view inside the strider. Tune in-game if it sits too high or too low.
		return new Vector3(0.0, 2.0, 0.0);
	}

	protected function canHavePassenger() : bool{
		return false; //a strider carries a single rider
	}

	protected function keepMovementWhileRidden() : bool{
		//keep server collision: the Bedrock client does NOT predict a WASD strider's physics like a boat, so dropping server
		//collision just makes it float and clip through blocks. The server stays authoritative over the strider's position.
		return false;
	}

	protected function lockRiderRotation() : bool{
		//sit astride the strider and look around freely, unlike a boat's hull-locked seat
		return false;
	}

	public function getRiderTrackingPosition(Vector3 $clientPredicted) : Vector3{
		//server-authoritative while ridden: the client doesn't predict the strider moving, so track the rider to the mob's
		//own server-moved position - the player then follows the strider as the server steers it, instead of staying put
		return $this->location->asVector3();
	}

	protected function isMovementFrozen() : bool{
		//suspend the wandering AI ONLY while the rider is actively steering (holding the stick) so it doesn't fight the input;
		//a rider with any other item (or empty-handed) just rides along while the strider keeps wandering on its own, as in vanilla
		return $this->rider !== null && $this->rider->getInventory()->getItemInHand() instanceof WarpedFungusOnAStick;
	}

	protected function dampMotionWhileFrozen() : bool{
		return false; //keep the rider's steering motion instead of zeroing it
	}

	public function isSaddled() : bool{
		return $this->saddled;
	}

	/** Urges the strider into a short burst of speed; triggered by right-clicking with the warped fungus on a stick. */
	public function boost() : void{
		$this->boostTicks = self::BOOST_TICKS;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		if(!$this->isBaby() && $this->rider === null){
			//saddle a bare adult strider
			if(!$this->saddled && $item->getTypeId() === ItemTypeIds::SADDLE){
				if($player->hasFiniteResources()){
					$item->pop();
					$player->getInventory()->setItemInHand($item);
				}
				$this->saddled = true;
				$this->networkPropertiesDirty = true;
				return true;
			}
			//mount a saddled strider with an empty hand or the control stick (the stick then steers it)
			if($this->saddled && ($item instanceof WarpedFungusOnAStick || $item->isNull())){
				Rideable::dismountFrom($player, false);
				$this->setRider($player);
				return true;
			}
		}
		return parent::onInteract($player, $clickPos); //warped fungus (the block) still tempts/breeds via Animal
	}

	public function handleVehicleInput(Player $player, PlayerAuthInputPacket $packet) : bool{
		if($this->rider !== $player){
			return false;
		}
		$vehicleInfo = $packet->getVehicleInfo();
		if($vehicleInfo !== null && $vehicleInfo->getPredictedVehicleActorUniqueId() !== $this->getId()){
			return false;
		}

		$yaw = $packet->getYaw();
		$this->setRotation($yaw, $this->location->pitch);

		//only a rider actually holding the warped fungus on a stick can steer it; otherwise they just sit
		if(!$player->getInventory()->getItemInHand() instanceof WarpedFungusOnAStick){
			return true;
		}
		$forward = $packet->getMoveVecZ();
		$strafe = $packet->getMoveVecX();
		if(abs($forward) > self::INPUT_DEADZONE || abs($strafe) > self::INPUT_DEADZONE){
			$speed = $this->boostTicks > 0 ? self::BOOST_SPEED : self::STEER_SPEED;
			$rad = deg2rad($yaw);
			//rotate the WASD/stick vector into world space; Y is left to gravity/lava buoyancy in tryChangeMovement so the
			//strider keeps riding the lava surface (and falls/walks normally on land) while the rider drives the heading
			$this->motion = $this->motion->withComponents(
				(-sin($rad) * $forward + cos($rad) * $strafe) * $speed,
				$this->motion->y,
				(cos($rad) * $forward + sin($rad) * $strafe) * $speed
			);
		}
		return true;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->closed || !$this->isAlive()){
			return $hasUpdate;
		}

		if($this->boostTicks > 0){
			$this->boostTicks -= $tickDiff;
			$hasUpdate = true;
		}

		//a rider who logged out, died or changed world can't keep steering
		if($this->rider !== null && ($this->rider->isClosed() || !$this->rider->isAlive() || $this->rider->getWorld() !== $this->getWorld())){
			$this->dismountRider();
		}

		//water is the strider's bane: in it the warm-blooded beast freezes and is hurt. CAUSE_CUSTOM so the drowningDamage
		//gamerule (often off on skyblock/creative) can't cancel it, matching the gamerule-independent vanilla strider chill
		if($this->isUnderwater() && ($this->ticksLived % 10) === 0){
			$this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_CUSTOM, 1));
		}

		return $hasUpdate;
	}

	protected function onDeath() : void{
		$this->dismountRider();
		parent::onDeath();
	}

	protected function onDispose() : void{
		$this->disposeRiding(); //chunk unload / world shutdown bypasses dismount - free the rider so they aren't stuck riding
		parent::onDispose();
	}

	/**
	 * Keeps the strider riding the surface of lava instead of sinking, so it appears to walk on top of it. Ported from
	 * the boat's server-side water-buoyancy settle but reading lava blocks: the body floats in a tight band around the
	 * lava surface while the AI (or its rider) walks it horizontally. Off lava it falls and moves under normal physics.
	 */
	protected function tryChangeMovement() : void{
		$lavaDiff = $this->getLavaLevel();
		if($lavaDiff === INF){
			parent::tryChangeMovement();
			return;
		}

		$mY = $this->motion->y;
		if($lavaDiff > self::SINKING_DEPTH && !$this->sinking){
			$this->sinking = true;
		}elseif($lavaDiff < -self::SINKING_DEPTH && $this->sinking){
			$this->sinking = false;
		}
		if($lavaDiff < -self::SINKING_DEPTH){
			$mY = min(0.05, $mY + 0.005);
		}elseif($lavaDiff < 0 || !$this->sinking){
			$mY = $mY > self::SINKING_MAX_SPEED ? max($mY - 0.02, self::SINKING_MAX_SPEED) : $mY + self::SINKING_SPEED;
		}
		if($lavaDiff > self::SINKING_DEPTH || $this->sinking){
			$mY = $lavaDiff > 0.5 ? $mY - $this->gravity : ($mY - self::SINKING_SPEED < -self::SINKING_MAX_SPEED ? $mY : $mY - self::SINKING_SPEED);
		}
		$friction = 1 - $this->drag;
		if($this->onGround){
			$friction *= $this->getWorld()->getBlockAt((int) floor($this->location->x), (int) floor($this->location->y - 1), (int) floor($this->location->z))->getFrictionFactor();
		}
		$this->motion = $this->motion->withComponents($this->motion->x * $friction, $mY, $this->motion->z * $friction);
	}

	/**
	 * Signed distance from the strider's feet down to the nearest lava surface touching its bounding box. Negative =
	 * sunk below the surface (rise), positive = above it, INF = no lava in the body (treated as on land). Mirrors the
	 * boat's getWaterLevel.
	 */
	private function getLavaLevel() : float{
		$maxY = $this->boundingBox->minY + self::LAVA_BASE_OFFSET;
		$diffY = INF;
		foreach($this->getBlocksAroundWithEntityInsideActions() as $block){
			if($block instanceof Lava){
				$level = ($block->getPosition()->getY() + 1) - ($block->getFluidHeightPercent() - 0.1111111);
				$diffY = min($maxY - $level, $diffY);
			}
		}
		return $diffY;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::SADDLED, $this->saddled);
		if($this->saddled){
			//server-authoritative ride: we do NOT set WASD_CONTROLLED. That flag makes the client predict the strider as its
			//own vehicle and then fight every server position update (it rubber-bands / floats), since the client does not
			//actually drive a strider like a boat. Without it the client accepts the server's position and we move the mob
			//ourselves from the rider's input. The seat number still marks the driving seat.
			$properties->setByte(EntityMetadataProperties::CONTROLLING_RIDER_SEAT_NUMBER, 0);
			//forbid the client from leaving the seat on its own (which it does on the first sneak without telling the server,
			//the double-shift bug): it must instead keep reporting the held SNEAKING flag, which the input handler turns into
			//a server-side dismount on the first shift
			$properties->setGenericFlag(EntityMetadataFlags::DOES_SERVER_AUTH_ONLY_DISMOUNT, true);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_SADDLED, $this->saddled ? 1 : 0);
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->saddled = $nbt->getByte(self::TAG_SADDLED, 0) !== 0;
	}

	public function getDrops() : array{
		$drops = [VanillaItems::STRING()->setCount(mt_rand(2, 5))];
		if($this->saddled){
			$drops[] = VanillaItems::SADDLE();
		}
		return $drops;
	}

	public function getXpDropAmount() : int{
		return mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::STRIDER_SPAWN_EGG();
	}
}
