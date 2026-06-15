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
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\sound\BowShootSound;
use function mt_rand;
use function sqrt;

/**
 * A crossbow-wielding illager: the ranged backbone of pillager patrols and raids. Hangs back and looses arrows from its
 * crossbow, taking a short windup between shots. (The crossbow item is not yet implemented, so it fires empty-handed.)
 */
class Pillager extends Raider{

	/** Ticks the pillager spends cranking its crossbow before each shot. */
	private const DRAW_TICKS = 20;

	public static function getNetworkTypeId() : string{ return EntityIds::PILLAGER; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.95, 0.6);
	}

	protected function getDefaultMaxHealth() : int{
		return 24;
	}

	public function getName() : string{
		return "Pillager";
	}

	protected function registerAttackGoals() : void{
		$this->addGoal(2, new RangedAttackGoal(
			fn(TargetCandidate $target) => $this->shootArrowAt($target),
			16.0,
			5.0,
			30,
			self::DRAW_TICKS
		));
	}

	private function shootArrowAt(TargetCandidate $target) : void{
		$origin = $this->getEyePos();
		$arrow = new Arrow(Location::fromObject($origin, $this->getWorld(), $this->location->getYaw(), $this->location->getPitch()), $this, false);

		$dx = $target->x - $origin->x;
		$dy = ($target->y + 1.0) - $origin->y;
		$dz = $target->z - $origin->z;
		$horizontal = sqrt(($dx * $dx) + ($dz * $dz));
		$direction = new Vector3($dx, $dy + $horizontal * 0.2, $dz);

		$arrow->setMotion($direction->normalize()->multiply(1.6));
		$arrow->spawnToAll();
		$this->broadcastAnimation(new ArmSwingAnimation($this));
		$this->broadcastSound(new BowShootSound());
	}

	public function getDrops() : array{
		return [
			VanillaItems::ARROW()->setCount(mt_rand(0, 2))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::PILLAGER_SPAWN_EGG();
	}
}
