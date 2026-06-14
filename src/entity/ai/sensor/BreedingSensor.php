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
use pocketmine\entity\Animal;
use pocketmine\entity\Living;
use pocketmine\math\AxisAlignedBB;

/**
 * Feeds two memory slots used by the breeding goals from a single nearby-entity scan:
 *  - BREED_TARGET: when this animal is an in-love adult, the nearest in-love adult of the same species (its mate).
 *  - PARENT: when this animal is a baby, the nearest adult of the same species (to trail).
 *
 * Only the relevant slot(s) are populated, so {@link \pocketmine\entity\ai\goal\BreedGoal} and FollowParentGoal stay idle
 * unless the animal is actually in love / a baby.
 */
final class BreedingSensor implements Sensor{

	public function __construct(
		private float $range = 8.0,
		private int $scanInterval = 10
	){}

	public function getScanIntervalTicks() : int{
		return $this->scanInterval;
	}

	public function sense(Living $owner, Memory $memory) : void{
		$wantMate = $owner instanceof Animal && !$owner->isBaby() && $owner->isInLove();
		$wantParent = $owner instanceof Animal && $owner->isBaby();

		if(!$owner instanceof Animal || (!$wantMate && !$wantParent)){
			$memory->erase(MemoryModuleType::BREED_TARGET);
			$memory->erase(MemoryModuleType::PARENT);
			return;
		}

		$pos = $owner->getPosition();
		$bb = new AxisAlignedBB(
			$pos->x - $this->range, $pos->y - $this->range, $pos->z - $this->range,
			$pos->x + $this->range, $pos->y + $this->range, $pos->z + $this->range
		);

		$bestMate = null;
		$bestMateDistSq = ($this->range * $this->range) + 1;
		$bestParent = null;
		$bestParentDistSq = ($this->range * $this->range) + 1;

		foreach($owner->getWorld()->getNearbyEntities($bb, $owner) as $entity){
			if(!$entity instanceof Animal || $entity::class !== $owner::class || !$entity->isAlive()){
				continue;
			}
			$ePos = $entity->getPosition();
			$distSq = $ePos->distanceSquared($pos);

			if($wantMate && !$entity->isBaby() && $entity->isInLove() && $distSq < $bestMateDistSq){
				$bestMateDistSq = $distSq;
				$bestMate = new TargetCandidate($entity->getId(), $ePos->x, $ePos->y, $ePos->z, true, false);
			}
			if($wantParent && !$entity->isBaby() && $distSq < $bestParentDistSq){
				$bestParentDistSq = $distSq;
				$bestParent = new TargetCandidate($entity->getId(), $ePos->x, $ePos->y, $ePos->z, true, false);
			}
		}

		if($bestMate !== null){
			$memory->set(MemoryModuleType::BREED_TARGET, $bestMate);
		}else{
			$memory->erase(MemoryModuleType::BREED_TARGET);
		}
		if($bestParent !== null){
			$memory->set(MemoryModuleType::PARENT, $bestParent);
		}else{
			$memory->erase(MemoryModuleType::PARENT);
		}
	}
}
