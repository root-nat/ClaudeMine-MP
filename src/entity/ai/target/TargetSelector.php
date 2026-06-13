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

namespace pocketmine\entity\ai\target;

use pocketmine\entity\ai\memory\Memory;
use pocketmine\entity\ai\memory\MemoryModuleType;
use pocketmine\entity\ai\MobContext;

/**
 * Chooses the mob's attack target from the candidates sensors wrote into {@link Memory}. Prefers the most recent
 * attacker (HURT_BY) if it is in range, otherwise the nearest living candidate within follow range. Pure: reads/writes
 * Memory and uses MobContext only for position/follow-range.
 */
final class TargetSelector{

	public function selectTarget(MobContext $mob) : ?TargetCandidate{
		$memory = $mob->getMemory();
		$pos = $mob->getPosition();
		$rangeSq = $mob->getFollowRange() ** 2;

		$hurtBy = $memory->get(MemoryModuleType::HURT_BY);
		if($hurtBy instanceof TargetCandidate && $hurtBy->alive && $hurtBy->distanceSquaredTo($pos->x, $pos->y, $pos->z) <= $rangeSq){
			$memory->set(MemoryModuleType::ATTACK_TARGET, $hurtBy);
			return $hurtBy;
		}

		$candidates = $memory->get(MemoryModuleType::NEAREST_ENTITIES);
		$best = null;
		$bestDistanceSq = $rangeSq;
		if(is_array($candidates)){
			foreach($candidates as $candidate){
				if(!($candidate instanceof TargetCandidate) || !$candidate->alive){
					continue;
				}
				$distanceSq = $candidate->distanceSquaredTo($pos->x, $pos->y, $pos->z);
				if($distanceSq <= $bestDistanceSq){
					$bestDistanceSq = $distanceSq;
					$best = $candidate;
				}
			}
		}

		if($best !== null){
			$memory->set(MemoryModuleType::ATTACK_TARGET, $best);
		}else{
			$memory->erase(MemoryModuleType::ATTACK_TARGET);
		}
		return $best;
	}
}
