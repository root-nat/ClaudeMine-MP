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
use pocketmine\entity\Piglin;
use pocketmine\item\Armor;
use pocketmine\item\VanillaArmorMaterials;
use pocketmine\math\AxisAlignedBB;
use pocketmine\player\Player;
use const INF;

/**
 * Targeting for a piglin: it hunts (a) anyone it holds a grudge against - spread through the sounder like the zombified
 * piglin's group anger - and otherwise, on sight, the nearest survival player NOT wearing a scrap of gold armour (gold
 * armour pacifies a piglin). A blow overrides gold: a provoked piglin and its kin chase the offender regardless of armour.
 * Resolves everything from a single getNearbyEntities scan.
 */
final class PiglinTargetSensor implements Sensor{

	public function __construct(
		private float $range,
		private int $scanInterval = 10
	){}

	public function getScanIntervalTicks() : int{
		return $this->scanInterval;
	}

	public function sense(Living $owner, Memory $memory) : void{
		if(!$owner instanceof Piglin){
			return;
		}
		$world = $owner->getWorld();
		$pos = $owner->getPosition();
		$bb = new AxisAlignedBB(
			$pos->x - $this->range, $pos->y - $this->range, $pos->z - $this->range,
			$pos->x + $this->range, $pos->y + $this->range, $pos->z + $this->range
		);

		//one pass: find an enraged kin's grudge to adopt, and the nearest unarmoured (no gold) survival player on sight
		$adoptTarget = null;
		$adoptRemaining = 0;
		$nearestBare = null;
		$nearestBareDistSq = INF;
		foreach($world->getNearbyEntities($bb, $owner) as $entity){
			if($entity instanceof Piglin){
				if($adoptTarget === null && $entity->isAngry()){
					$matesTarget = $entity->getAngerTargetId();
					if($matesTarget !== null){
						$victim = $world->getEntity($matesTarget);
						if($victim instanceof Player && $victim->isAlive() && $victim->isSurvival()){
							$adoptTarget = $matesTarget;
							$adoptRemaining = $entity->getRemainingAngerTicks();
						}
					}
				}
			}elseif($entity instanceof Player && $entity->isAlive() && $entity->isSurvival() && !self::wearsGold($entity)){
				$distSq = $entity->getPosition()->distanceSquared($pos);
				if($distSq < $nearestBareDistSq){
					$nearestBareDistSq = $distSq;
					$nearestBare = $entity;
				}
			}
		}

		//a calm piglin adopts an enraged kin's grudge, copying its remaining time (so the group's anger only counts down)
		if(!$owner->isAngry() && $adoptTarget !== null){
			$owner->angerAt($adoptTarget, $adoptRemaining);
		}

		//a held grudge takes priority and ignores gold armour
		if($owner->isAngry()){
			$targetId = $owner->getAngerTargetId();
			$victim = $targetId !== null ? $world->getEntity($targetId) : null;
			if($victim instanceof Player && $victim->isAlive() && $victim->isSurvival()){
				$vPos = $victim->getPosition();
				$memory->set(MemoryModuleType::NEAREST_ENTITIES, [new TargetCandidate($victim->getId(), $vPos->x, $vPos->y, $vPos->z, true, true)]);
				return;
			}
			if($victim !== null){
				//dead / no-longer-survival / id-reused -> forgive; a merely unresolvable target keeps the grudge (timer)
				$owner->calmDown();
			}
		}

		//on sight: hunt the nearest survival player wearing no gold
		if($nearestBare !== null){
			$nPos = $nearestBare->getPosition();
			$memory->set(MemoryModuleType::NEAREST_ENTITIES, [new TargetCandidate($nearestBare->getId(), $nPos->x, $nPos->y, $nPos->z, true, true)]);
		}else{
			$memory->set(MemoryModuleType::NEAREST_ENTITIES, []);
		}
	}

	private static function wearsGold(Player $player) : bool{
		foreach($player->getArmorInventory()->getContents() as $piece){
			if($piece instanceof Armor && $piece->getMaterial() === VanillaArmorMaterials::GOLD()){
				return true;
			}
		}
		return false;
	}
}
