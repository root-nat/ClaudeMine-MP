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

use pocketmine\block\VanillaBlocks;
use pocketmine\block\Water;
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
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\player\Player;
use pocketmine\world\sound\BlockBreakSound;
use function abs;
use function array_filter;
use function array_map;
use function array_values;
use function cos;
use function deg2rad;
use function floor;
use function max;
use function min;
use function sin;
use const INF;

class Boat extends Rideable{

	private const TAG_WOOD_TYPE = "PMMPBoatWoodType";
	/** Per-tick paddle speed in/out of water - the rider's input is turned into motion at this rate (RootMine values). */
	private const WATER_SPEED = 0.18;
	private const LAND_SPEED = 0.08;
	/** How far the input must deviate from neutral before it counts as steering (deadzone for analog sticks). */
	private const INPUT_DEADZONE = 0.01;
	/** Reference height above the hull's base used to measure distance to the water surface (RootMine value). */
	private const BASE_OFFSET = 0.375;
	/** Water-surface band and settle speeds for an EMPTY boat's server-side buoyancy (RootMine values). */
	private const SINKING_DEPTH = 0.07;
	private const SINKING_SPEED = 0.0005;
	private const SINKING_MAX_SPEED = 0.005;

	protected int $woodType = 0;
	/** Hysteresis flag for the empty-boat settle so it doesn't oscillate across the surface band. */
	protected bool $sinking = false;
	protected float $paddleTimeLeft = 0.0;
	protected float $paddleTimeRight = 0.0;

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
	 * Steers the boat from its rider's authoritative input, the server-driven model used by RootMine-MP: the rider's body
	 * yaw becomes the hull heading and the raw WASD/stick vector (moveVecZ forward, moveVecX strafe) is rotated by that
	 * yaw into horizontal motion, faster on water than land. The vertical axis is left to buoyancy (entityBaseTick) so the
	 * hull never leaves the waterline. The actual position change happens through the normal move() in onUpdate; we only
	 * set the motion here. Called by the network handler for the controlling player each input tick.
	 */
	public function handleVehicleInput(Player $player, PlayerAuthInputPacket $packet) : bool{
		if($this->rider !== $player){
			return false;
		}
		$vehicleInfo = $packet->getVehicleInfo();
		if($vehicleInfo !== null && $vehicleInfo->getPredictedVehicleActorUniqueId() !== $this->getId()){
			return false;
		}

		$yaw = $packet->getYaw();
		$this->setRotation($yaw, 0.0);

		$forward = $packet->getMoveVecZ();
		$strafe = $packet->getMoveVecX();
		if(abs($forward) > self::INPUT_DEADZONE || abs($strafe) > self::INPUT_DEADZONE){
			$speed = $this->getWaterLevel() !== INF ? self::WATER_SPEED : self::LAND_SPEED;
			$rad = deg2rad($yaw);
			//rotate the input vector into world space. Y is forced to 0: while ridden the client owns the vertical axis
			//(IS_BUOYANT), so the server must not feed it any vertical motion or it fights the client's bob/wave prediction
			$this->motion = $this->motion->withComponents(
				(-sin($rad) * $forward + cos($rad) * $strafe) * $speed,
				0.0,
				(cos($rad) * $forward + sin($rad) * $strafe) * $speed
			);
			$this->paddle();
		}else{
			//no input: bleed off the drift so the boat coasts to a stop instead of sliding forever (collision is off)
			$this->motion = $this->motion->withComponents($this->motion->x * 0.4, 0.0, $this->motion->z * 0.4);
		}
		$this->scheduleUpdate();
		return true;
	}

	/**
	 * Signed distance from the hull's reference height down to the nearest water surface touching its bounding box, ported
	 * from RootMine. Negative = submerged (should rise), positive = riding above the surface, INF = no water in the hull
	 * volume (treated as on land). Sampling the whole hull volume - not just the single origin block - keeps the water/land
	 * decision stable as the boat bobs, so paddle speed and buoyancy don't flicker at the waterline. Shared by buoyancy and
	 * steering.
	 */
	private function getWaterLevel() : float{
		$maxY = $this->boundingBox->minY + self::BASE_OFFSET;
		$diffY = INF;
		foreach($this->getBlocksAroundWithEntityInsideActions() as $block){
			if($block instanceof Water){
				$level = ($block->getPosition()->getY() + 1) - ($block->getFluidHeightPercent() - 0.1111111);
				$diffY = min($maxY - $level, $diffY);
			}
		}
		return $diffY;
	}

	/** Advances the oar animation timers the Bedrock client reads to swing the paddles while rowing. */
	private function paddle() : void{
		$this->paddleTimeLeft += 0.2;
		$this->paddleTimeRight += 0.2;
		$this->networkPropertiesDirty = true;
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

		//vertical motion is no longer handled here: while ridden the client floats the boat (IS_BUOYANT), and while empty
		//tryChangeMovement runs the server-side water-level settle. Mixing a server push in here is what fought the client.
		return $hasUpdate;
	}

	/**
	 * While RIDDEN, do nothing: the client owns ALL of the boat's physics - WASD_CONTROLLED for steering and IS_BUOYANT for
	 * the vertical bob/waves. The server must only apply the input motion set in handleVehicleInput and never add its own
	 * gravity/friction/buoyancy, or it fights the client's prediction (the jerky steering + dismount snap-back). While EMPTY
	 * (no client predicting it) run RootMine's server-side water-level settle so a loose boat drifts to and rests on the
	 * surface, and falls under gravity on land.
	 */
	protected function tryChangeMovement() : void{
		if($this->rider !== null){
			return;
		}
		$mY = $this->motion->y;
		$waterDiff = $this->getWaterLevel();
		if($waterDiff > self::SINKING_DEPTH && !$this->sinking){
			$this->sinking = true;
		}elseif($waterDiff < -self::SINKING_DEPTH && $this->sinking){
			$this->sinking = false;
		}
		if($waterDiff < -self::SINKING_DEPTH){
			$mY = min(0.05, $mY + 0.005);
		}elseif($waterDiff < 0 || !$this->sinking){
			$mY = $mY > self::SINKING_MAX_SPEED ? max($mY - 0.02, self::SINKING_MAX_SPEED) : $mY + self::SINKING_SPEED;
		}
		//self-eject if the hull ends up embedded in a solid block (no-op when floating in water/air); RootMine parity
		$this->checkObstruction($this->location->x, $this->location->y, $this->location->z);
		if($waterDiff > self::SINKING_DEPTH || $this->sinking){
			$mY = $waterDiff > 0.5 ? $mY - $this->gravity : ($mY - self::SINKING_SPEED < -self::SINKING_MAX_SPEED ? $mY : $mY - self::SINKING_SPEED);
		}
		$friction = 1 - $this->drag;
		if($this->onGround){
			$friction *= $this->getWorld()->getBlockAt((int) floor($this->location->x), (int) floor($this->location->y - 1), (int) floor($this->location->z))->getFrictionFactor();
		}
		$this->motion = $this->motion->withComponents($this->motion->x * $friction, $mY, $this->motion->z * $friction);
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
		$properties->setFloat(EntityMetadataProperties::PADDLE_TIME_LEFT, $this->paddleTimeLeft);
		$properties->setFloat(EntityMetadataProperties::PADDLE_TIME_RIGHT, $this->paddleTimeRight);
		//the full Bedrock "player-steered, self-floating vehicle" profile, matching vanilla/RootMine. The client predicts
		//BOTH the WASD steering (WASD_CONTROLLED) AND the vertical bob/waves (IS_BUOYANT + BUOYANCY_DATA, with gravity
		//handled inside the buoyancy sim so the generic AFFECTED_BY_GRAVITY flag is off). Sending only WASD_CONTROLLED left
		//the client half-predicting and fighting the server - the jerky steering and the dismount snap-back. With the full
		//set the client owns the physics and the server only feeds input motion (see tryChangeMovement / handleVehicleInput).
		$properties->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, true);
		$properties->setGenericFlag(EntityMetadataFlags::HAS_COLLISION, true);
		$properties->setGenericFlag(EntityMetadataFlags::AFFECTED_BY_GRAVITY, false);
		$properties->setByte(EntityMetadataProperties::CONTROLLING_RIDER_SEAT_NUMBER, 0);
		$properties->setByte(EntityMetadataProperties::IS_BUOYANT, 1);
		$properties->setString(EntityMetadataProperties::BUOYANCY_DATA, '{"apply_gravity":true,"base_buoyancy":1.0,"big_wave_probability":0.03,"big_wave_speed":10.0,"drag_down_on_buoyancy_removed":0.0,"liquid_blocks":["minecraft:water","minecraft:flowing_water"],"simulate_waves":true}');
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

	protected function onDispose() : void{
		$this->disposeRiding(); //chunk unload / world shutdown bypasses dismount - free the rider/passenger so they aren't stuck
		parent::onDispose();
	}
}
