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
use pocketmine\entity\Creeper;
use pocketmine\entity\Living;
use pocketmine\entity\Monster;
use pocketmine\math\AxisAlignedBB;

/**
 * Records nearby hostile {@link Monster}s within range into NEAREST_ENTITIES so the target selector can pick one to fight.
 * Used by guardians such as the iron golem. Creepers are spared (a golem won't pick a fight that ends in an explosion).
 */
final class NearestMonstersSensor implements Sensor{

	public function __construct(
		private float $range,
		private int $scanInterval = 10
	){}

	public function getScanIntervalTicks() : int{
		return $this->scanInterval;
	}

	public function sense(Living $owner, Memory $memory) : void{
		$pos = $owner->getPosition();
		$bb = new AxisAlignedBB(
			$pos->x - $this->range, $pos->y - $this->range, $pos->z - $this->range,
			$pos->x + $this->range, $pos->y + $this->range, $pos->z + $this->range
		);

		$candidates = [];
		foreach($owner->getWorld()->getNearbyEntities($bb, $owner) as $entity){
			if(!$entity instanceof Monster || $entity instanceof Creeper || !$entity->isAlive()){
				continue;
			}
			$ePos = $entity->getPosition();
			$candidates[] = new TargetCandidate($entity->getId(), $ePos->x, $ePos->y, $ePos->z, true, false);
		}

		$memory->set(MemoryModuleType::NEAREST_ENTITIES, $candidates);
	}
}
