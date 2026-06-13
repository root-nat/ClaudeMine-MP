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

namespace pocketmine\entity\ai\sensor;

use pocketmine\entity\ai\memory\Memory;
use pocketmine\entity\ai\memory\MemoryModuleType;
use pocketmine\entity\ai\target\TargetCandidate;
use pocketmine\entity\Living;

/**
 * Records the entity that most recently damaged the mob (its last damage cause) into HURT_BY, with a TTL so the grudge
 * fades. Lets neutral mobs retaliate. Reads only the owner's stored last-damage-by-entity event.
 */
final class HurtBySensor implements Sensor{

	public function __construct(
		private int $memoryTtl = 200,
		private int $scanInterval = 1
	){}

	public function getScanIntervalTicks() : int{
		return $this->scanInterval;
	}

	public function sense(Living $owner, Memory $memory) : void{
		$cause = $owner->getLastDamageCause();
		if($cause instanceof \pocketmine\event\entity\EntityDamageByEntityEvent){
			$damager = $cause->getDamager();
			if($damager instanceof Living && $damager->isAlive()){
				$pos = $damager->getPosition();
				$memory->set(
					MemoryModuleType::HURT_BY,
					new TargetCandidate($damager->getId(), $pos->x, $pos->y, $pos->z, true, $damager instanceof \pocketmine\player\Player),
					$this->memoryTtl
				);
			}
		}
	}
}
