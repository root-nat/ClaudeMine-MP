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

use pocketmine\entity\ai\target\TargetCandidate;
use pocketmine\entity\projectile\SmallFireball;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;

/**
 * Nether ranged mob: it hovers, drifts toward its target and spits bursts of {@link SmallFireball}s that set victims
 * alight. Immune to fire and lava.
 */
class Blaze extends FlyingShooterMonster{

	public static function getNetworkTypeId() : string{ return EntityIds::BLAZE; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.8, 0.6);
	}

	public function getName() : string{
		return "Blaze";
	}

	protected function attackCooldownTicks() : int{ return 60; }

	protected function shotsPerVolley() : int{ return 3; }

	protected function approachDistance() : float{ return 7.0; }

	protected function shootRange() : float{ return 16.0; }

	protected function fireProjectile(TargetCandidate $target) : void{
		$origin = $this->getEyePos();
		$fireball = new SmallFireball(Location::fromObject($origin, $this->getWorld(), $this->location->getYaw(), $this->location->getPitch()), $this);

		$direction = (new Vector3($target->x - $origin->x, ($target->y + 1.0) - $origin->y, $target->z - $origin->z))->normalize();
		//a little scatter so a burst spreads out like vanilla
		$direction = $direction->add(
			(mt_rand(-100, 100) / 1000),
			(mt_rand(-100, 100) / 1000),
			(mt_rand(-100, 100) / 1000)
		);
		$fireball->setMotion($direction->normalize()->multiply(0.85));
		$fireball->spawnToAll();
	}

	public function getDrops() : array{
		return [VanillaItems::BLAZE_ROD()->setCount(mt_rand(0, 1))];
	}

	public function getXpDropAmount() : int{
		return 10;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::BLAZE_SPAWN_EGG();
	}
}
