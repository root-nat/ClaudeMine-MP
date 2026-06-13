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

use pocketmine\entity\ai\MobContext;
use function usort;

/**
 * Priority + flag-mutex goal arbitration faithful to Bedrock/Java: each tick it stops running goals that can no longer
 * continue, lets higher-priority goals preempt lower-priority ones that share an action flag, starts newly-eligible
 * goals on free flags, then ticks everything still running. Pure: drives goals through {@link MobContext}.
 */
final class GoalSelector{

	/** @var PrioritizedGoal[] sorted by priority ascending (most important first) */
	private array $goals = [];

	public function add(int $priority, Goal $goal) : PrioritizedGoal{
		$wrapped = new PrioritizedGoal($priority, $goal);
		$this->goals[] = $wrapped;
		usort($this->goals, fn(PrioritizedGoal $a, PrioritizedGoal $b) => $a->priority <=> $b->priority);
		return $wrapped;
	}

	/**
	 * @return PrioritizedGoal[]
	 */
	public function getGoals() : array{
		return $this->goals;
	}

	public function tick(MobContext $mob) : void{
		//1. stop running goals that can no longer continue
		foreach($this->goals as $pg){
			if($pg->isRunning() && !$pg->goal->canContinueToUse($mob)){
				$pg->stop($mob);
			}
		}

		//2. compute which flags are held, mapped to their owning (still-running) goal
		/** @var array<int, PrioritizedGoal> $flagOwner */
		$flagOwner = [];
		foreach($this->goals as $pg){
			if($pg->isRunning()){
				$this->assignFlags($flagOwner, $pg);
			}
		}

		//3. start eligible goals in priority order, preempting lower-priority owners of contended flags
		foreach($this->goals as $pg){
			if($pg->isRunning() || !$pg->goal->canUse($mob)){
				continue;
			}
			$flags = $pg->getFlags();
			$blocked = false;
			/** @var PrioritizedGoal[] $toPreempt */
			$toPreempt = [];
			foreach($this->flagBits($flags) as $bit){
				$owner = $flagOwner[$bit] ?? null;
				if($owner !== null){
					if($owner->priority <= $pg->priority){
						$blocked = true; //held by an equal/higher-priority goal
						break;
					}
					$toPreempt[$owner->priority . ":" . $bit] = $owner;
				}
			}
			if($blocked){
				continue;
			}
			foreach($toPreempt as $owner){
				$owner->stop($mob);
				$this->releaseFlags($flagOwner, $owner);
			}
			$pg->start($mob);
			$this->assignFlags($flagOwner, $pg);
		}

		//4. tick everything still running
		foreach($this->goals as $pg){
			$pg->tick($mob);
		}
	}

	/**
	 * @param array<int, PrioritizedGoal> $flagOwner
	 */
	private function assignFlags(array &$flagOwner, PrioritizedGoal $pg) : void{
		foreach($this->flagBits($pg->getFlags()) as $bit){
			$flagOwner[$bit] = $pg;
		}
	}

	/**
	 * @param array<int, PrioritizedGoal> $flagOwner
	 */
	private function releaseFlags(array &$flagOwner, PrioritizedGoal $pg) : void{
		foreach($flagOwner as $bit => $owner){
			if($owner === $pg){
				unset($flagOwner[$bit]);
			}
		}
	}

	/**
	 * @return int[]
	 */
	private function flagBits(int $flags) : array{
		$bits = [];
		for($bit = 1; $bit <= $flags; $bit <<= 1){
			if(($flags & $bit) !== 0){
				$bits[] = $bit;
			}
		}
		return $bits;
	}
}
