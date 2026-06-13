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
use pocketmine\entity\projectile\Arrow;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\sound\BowShootSound;
use function mt_rand;
use function sqrt;

class Skeleton extends Monster{

	public static function getNetworkTypeId() : string{ return EntityIds::SKELETON; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.99, 0.6);
	}

	public function getName() : string{
		return "Skeleton";
	}

	protected function registerAttackGoals() : void{
		$this->addGoal(2, new RangedAttackGoal(fn(TargetCandidate $target) => $this->shootArrowAt($target)));
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
		$this->broadcastSound(new BowShootSound());
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
