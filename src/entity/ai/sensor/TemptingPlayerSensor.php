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
use pocketmine\player\Player;

/**
 * Records the nearest survival player holding this animal's breeding food into TEMPTING_PLAYER, so {@link \pocketmine\entity\ai\goal\TemptGoal}
 * can make the animal follow them. The only world access is World::getNearbyEntities and reading the player's held item.
 */
final class TemptingPlayerSensor implements Sensor{

	public function __construct(
		private float $range = 10.0,
		private int $scanInterval = 10
	){}

	public function getScanIntervalTicks() : int{
		return $this->scanInterval;
	}

	public function sense(Living $owner, Memory $memory) : void{
		if(!$owner instanceof Animal){
			$memory->erase(MemoryModuleType::TEMPTING_PLAYER);
			return;
		}

		$pos = $owner->getPosition();
		$bb = new AxisAlignedBB(
			$pos->x - $this->range, $pos->y - $this->range, $pos->z - $this->range,
			$pos->x + $this->range, $pos->y + $this->range, $pos->z + $this->range
		);

		$best = null;
		$bestDistSq = ($this->range * $this->range) + 1;
		foreach($owner->getWorld()->getNearbyEntities($bb, $owner) as $entity){
			if(!$entity instanceof Player || !$entity->isAlive() || !$entity->isSurvival()){
				continue;
			}
			if(!$owner->isBreedingFood($entity->getInventory()->getItemInHand())){
				continue;
			}
			$ePos = $entity->getPosition();
			$distSq = $ePos->distanceSquared($pos);
			if($distSq < $bestDistSq){
				$bestDistSq = $distSq;
				$best = new TargetCandidate($entity->getId(), $ePos->x, $ePos->y, $ePos->z, true, true);
			}
		}

		if($best !== null){
			$memory->set(MemoryModuleType::TEMPTING_PLAYER, $best);
		}else{
			$memory->erase(MemoryModuleType::TEMPTING_PLAYER);
		}
	}
}
