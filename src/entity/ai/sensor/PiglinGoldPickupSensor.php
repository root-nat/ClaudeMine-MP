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
use pocketmine\entity\object\ItemEntity;
use pocketmine\entity\Piglin;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\AxisAlignedBB;

/**
 * Records the nearest dropped gold ingot a piglin wants to fetch into PICKUP_TARGET, so it walks over and barters it -
 * the iconic "throw gold to a piglin" trade. Only scans while the piglin is calm and not already admiring a piece, and
 * erases the slot otherwise. The only world access is World::getNearbyEntities.
 */
final class PiglinGoldPickupSensor implements Sensor{

	public function __construct(
		private float $range,
		private int $scanInterval = 10
	){}

	public function getScanIntervalTicks() : int{
		return $this->scanInterval;
	}

	public function sense(Living $owner, Memory $memory) : void{
		if(!$owner instanceof Piglin || !$owner->wantsBarterPickup()){
			$memory->erase(MemoryModuleType::PICKUP_TARGET);
			return;
		}

		$pos = $owner->getPosition();
		$bb = new AxisAlignedBB(
			$pos->x - $this->range, $pos->y - $this->range, $pos->z - $this->range,
			$pos->x + $this->range, $pos->y + $this->range, $pos->z + $this->range
		);

		$best = null;
		$bestDistSq = $this->range ** 2;
		foreach($owner->getWorld()->getNearbyEntities($bb, $owner) as $entity){
			if(!$entity instanceof ItemEntity || $entity->isFlaggedForDespawn()){
				continue;
			}
			if($entity->getItem()->getTypeId() !== ItemTypeIds::GOLD_INGOT){
				continue;
			}
			$ePos = $entity->getPosition();
			$distSq = $ePos->distanceSquared($pos);
			if($distSq <= $bestDistSq){
				$bestDistSq = $distSq;
				$best = new TargetCandidate($entity->getId(), $ePos->x, $ePos->y, $ePos->z, true, false);
			}
		}

		if($best !== null){
			//TTL = one scan interval so a candidate self-expires if the item vanishes between scans (eaten by a player,
			//merged, despawned); otherwise its stale alive=true snapshot would keep the pickup goal preempting combat
			$memory->set(MemoryModuleType::PICKUP_TARGET, $best, $this->scanInterval);
		}else{
			$memory->erase(MemoryModuleType::PICKUP_TARGET);
		}
	}
}
