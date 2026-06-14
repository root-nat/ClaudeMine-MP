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

namespace pocketmine\entity\projectile;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\Explosion;
use pocketmine\world\Position;

/**
 * The large explosive fireball a Ghast shoots: flies straight and detonates a small explosion wherever it lands.
 */
class Fireball extends Throwable{

	private const EXPLOSION_RADIUS = 1.0;

	public static function getNetworkTypeId() : string{ return EntityIds::FIREBALL; }

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.0, 1.0); }

	protected function getInitialGravity() : float{ return 0.0; }

	protected function onHit(ProjectileHitEvent $event) : void{
		$ev = new EntityPreExplodeEvent($this, self::EXPLOSION_RADIUS);
		$ev->call();
		if($ev->isCancelled()){
			return;
		}
		$explosion = new Explosion(Position::fromObject($this->location, $this->getWorld()), $ev->getRadius(), $this, $ev->getFireChance());
		if($ev->isBlockBreaking()){
			$explosion->explodeA();
		}
		$explosion->explodeB();
	}
}
