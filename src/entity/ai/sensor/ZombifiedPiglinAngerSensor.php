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
use pocketmine\entity\ZombifiedPiglin;
use pocketmine\math\AxisAlignedBB;
use pocketmine\player\Player;

/**
 * Drives the zombified piglin's signature group anger. A piglin is neutral until provoked (its own damage hook sets the
 * anger). This sensor (a) makes a calm piglin ADOPT the anger of an enraged pack-mate nearby, so a single struck piglin
 * drags the whole sounder into the fight, and (b) while angry, writes the offending player into NEAREST_ENTITIES so the
 * melee goal hunts them - clearing the target only when the offender is gone. The only world access is getNearbyEntities.
 */
final class ZombifiedPiglinAngerSensor implements Sensor{

	public function __construct(
		private float $range,
		private int $scanInterval = 10
	){}

	public function getScanIntervalTicks() : int{
		return $this->scanInterval;
	}

	public function sense(Living $owner, Memory $memory) : void{
		if(!$owner instanceof ZombifiedPiglin){
			return;
		}
		$world = $owner->getWorld();

		//a calm piglin near an enraged pack-mate adopts that mate's grudge - this is how the anger spreads through a group
		if(!$owner->isAngry()){
			$pos = $owner->getPosition();
			$bb = new AxisAlignedBB(
				$pos->x - $this->range, $pos->y - $this->range, $pos->z - $this->range,
				$pos->x + $this->range, $pos->y + $this->range, $pos->z + $this->range
			);
			foreach($world->getNearbyEntities($bb, $owner) as $entity){
				if($entity instanceof ZombifiedPiglin && $entity->isAngry()){
					$matesTarget = $entity->getAngerTargetId();
					if($matesTarget !== null){
						$victim = $world->getEntity($matesTarget);
						if($victim instanceof Player && $victim->isAlive() && $victim->isSurvival()){
							//copy the mate's REMAINING anger, not a fresh duration, so adopted grudges only count down -
							//otherwise pack-mates keep re-arming each other and the group stays enraged forever
							$owner->angerAt($matesTarget, $entity->getRemainingAngerTicks());
							break;
						}
					}
				}
			}
		}

		//while angry, hunt the offender if they're a reachable survival player; otherwise hold no target this scan
		if($owner->isAngry()){
			$targetId = $owner->getAngerTargetId();
			$victim = $targetId !== null ? $world->getEntity($targetId) : null;
			if($victim instanceof Player && $victim->isAlive() && $victim->isSurvival()){
				$vPos = $victim->getPosition();
				$memory->set(MemoryModuleType::NEAREST_ENTITIES, [new TargetCandidate($victim->getId(), $vPos->x, $vPos->y, $vPos->z, true, true)]);
				return;
			}
			if($victim !== null){
				//resolved to a dead / no-longer-survival / id-reused entity: forgive. A merely UNRESOLVABLE target (player
				//far away, in another world, or relogged) keeps the grudge - only the anger timer (entityBaseTick) ends it
				$owner->calmDown();
			}
		}

		$memory->set(MemoryModuleType::NEAREST_ENTITIES, []);
	}
}
