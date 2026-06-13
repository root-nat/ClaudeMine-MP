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

namespace pocketmine\entity\ai\goal;

use pocketmine\entity\ai\memory\MemoryModuleType;
use pocketmine\entity\ai\MobContext;
use pocketmine\entity\ai\target\TargetCandidate;
use function is_array;

/**
 * Occasionally turns the mob's head to watch the nearest player for a short while. Look-only, so it runs alongside
 * movement goals. Pure: reads NEAREST_PLAYERS from memory, drives MobContext::lookAt.
 */
final class LookAtPlayerGoal extends BaseGoal{
	private const START_CHANCE = 0.02;

	private int $lookTicksRemaining = 0;
	private ?TargetCandidate $watching = null;

	public function __construct(
		private float $range = 8.0,
		private int $minDuration = 40,
		private int $maxDuration = 80
	){}

	public function getFlags() : int{
		return GoalFlag::LOOK;
	}

	public function canUse(MobContext $mob) : bool{
		if($mob->getRandomFloat() > self::START_CHANCE){
			return false;
		}
		$this->watching = $this->nearestPlayer($mob);
		return $this->watching !== null;
	}

	public function canContinueToUse(MobContext $mob) : bool{
		return $this->lookTicksRemaining > 0 && $this->watching !== null && $this->watching->alive;
	}

	public function start(MobContext $mob) : void{
		$span = $this->maxDuration - $this->minDuration;
		$this->lookTicksRemaining = $this->minDuration + (int) ($mob->getRandomFloat() * $span);
	}

	public function stop(MobContext $mob) : void{
		$this->watching = null;
		$this->lookTicksRemaining = 0;
	}

	public function tick(MobContext $mob) : void{
		if($this->watching !== null){
			$mob->lookAt($this->watching->position());
		}
		if($this->lookTicksRemaining > 0){
			--$this->lookTicksRemaining;
		}
	}

	private function nearestPlayer(MobContext $mob) : ?TargetCandidate{
		$players = $mob->getMemory()->get(MemoryModuleType::NEAREST_PLAYERS);
		if(!is_array($players)){
			return null;
		}
		$pos = $mob->getPosition();
		$rangeSq = $this->range ** 2;
		$best = null;
		$bestDistanceSq = $rangeSq;
		foreach($players as $player){
			if(!($player instanceof TargetCandidate) || !$player->alive){
				continue;
			}
			$distanceSq = $player->distanceSquaredTo($pos->x, $pos->y, $pos->z);
			if($distanceSq <= $bestDistanceSq){
				$bestDistanceSq = $distanceSq;
				$best = $player;
			}
		}
		return $best;
	}
}
