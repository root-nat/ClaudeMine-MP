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
use pocketmine\entity\projectile\Fireball;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;

/**
 * Large floating nether mob that lobs slow, explosive {@link Fireball}s at its target from a long way off. Fragile (low
 * health) and immune to fire/lava.
 */
class Ghast extends FlyingShooterMonster{

	public static function getNetworkTypeId() : string{ return EntityIds::GHAST; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(4.0, 4.0);
	}

	protected function getDefaultMaxHealth() : int{
		return 10;
	}

	public function getName() : string{
		return "Ghast";
	}

	protected function attackCooldownTicks() : int{ return 60; }

	protected function shotsPerVolley() : int{ return 1; }

	protected function approachDistance() : float{ return 12.0; }

	protected function shootRange() : float{ return 64.0; }

	protected function fireProjectile(TargetCandidate $target) : void{
		$origin = $this->getEyePos();
		$fireball = new Fireball(Location::fromObject($origin, $this->getWorld(), $this->location->getYaw(), $this->location->getPitch()), $this);

		$direction = new Vector3($target->x - $origin->x, ($target->y + 1.0) - $origin->y, $target->z - $origin->z);
		$fireball->setMotion($direction->normalize()->multiply(0.6));
		$fireball->spawnToAll();
	}

	public function getDrops() : array{
		return [
			VanillaItems::GUNPOWDER()->setCount(mt_rand(0, 2)),
			VanillaItems::GHAST_TEAR()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::GHAST_SPAWN_EGG();
	}
}
