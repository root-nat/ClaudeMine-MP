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

use pocketmine\entity\ai\goal\RangedAttackGoal;
use pocketmine\entity\ai\target\TargetCandidate;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\projectile\Arrow;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use pocketmine\world\sound\BowShootSound;
use function mt_rand;
use function sqrt;

class Skeleton extends Monster{

	/** Ticks the skeleton visibly draws its bow before loosing each arrow (long enough for the bow-pull to reach full). */
	private const DRAW_TICKS = 25;

	/** Whether the skeleton is currently drawing its bow (drives the aiming pose flag). */
	private bool $aiming = false;

	public static function getNetworkTypeId() : string{ return EntityIds::SKELETON; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.99, 0.6);
	}

	public function getName() : string{
		return "Skeleton";
	}

	protected function registerAttackGoals() : void{
		//draw the bow for DRAW_TICKS before each shot, then a short cooldown, so it charges-then-fires like vanilla
		$this->addGoal(2, new RangedAttackGoal(
			fn(TargetCandidate $target) => $this->shootArrowAt($target),
			12.0,
			4.0,
			20,
			self::DRAW_TICKS,
			fn(bool $drawing) => $this->setAiming($drawing)
		));
	}

	protected function burnsInDaylight() : bool{
		return true; //skeletons catch fire in the morning sun
	}

	private function setAiming(bool $aiming) : void{
		if($aiming === $this->aiming){
			return;
		}
		$this->aiming = $aiming;
		$this->networkPropertiesDirty = true;
	}

	/**
	 * Creates the arrow this skeleton looses. Override for variants that fire special arrows (e.g. a Stray's slowness arrow).
	 */
	protected function createArrow(Location $location) : Arrow{
		return new Arrow($location, $this, false);
	}

	private function shootArrowAt(TargetCandidate $target) : void{
		$origin = $this->getEyePos();
		$arrow = $this->createArrow(Location::fromObject($origin, $this->getWorld(), $this->location->getYaw(), $this->location->getPitch()));

		$dx = $target->x - $origin->x;
		$dy = ($target->y + 1.0) - $origin->y;
		$dz = $target->z - $origin->z;
		$horizontal = sqrt(($dx * $dx) + ($dz * $dz));
		$direction = new Vector3($dx, $dy + $horizontal * 0.2, $dz);

		$arrow->setMotion($direction->normalize()->multiply(1.6));
		$arrow->spawnToAll();
		//visibly loose the arrow: the skeleton swings its bow arm as the shot leaves
		$this->broadcastAnimation(new ArmSwingAnimation($this));
		$this->broadcastSound(new BowShootSound());
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);

		//put a bow in the skeleton's hand so every viewer sees it armed (Living mobs have no hand inventory of their own)
		$session = $player->getNetworkSession();
		$session->sendDataPacket(MobEquipmentPacket::create(
			$this->getId(),
			ItemStackWrapper::legacy($session->getTypeConverter()->coreItemStackToNet(VanillaItems::BOW())),
			0,
			0,
			ContainerIds::INVENTORY
		));
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		//ACTION = "using held item": with a bow in hand the client renders the progressive bow-draw/pull pose - the same
		//flag the engine uses to show a player drawing a bow to other players. Held true only during the draw windup.
		//(FACING_TARGET_TO_RANGE_ATTACK, the obvious-by-name flag, does NOT drive the vanilla skeleton model.)
		$properties->setGenericFlag(EntityMetadataFlags::ACTION, $this->aiming);
	}

	public function getDrops() : array{
		return [
			VanillaItems::ARROW()->setCount(mt_rand(0, 2)),
			VanillaItems::BONE()->setCount(mt_rand(0, 2))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::SKELETON_SPAWN_EGG();
	}
}
