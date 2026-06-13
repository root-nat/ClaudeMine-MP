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

/**
 * Wraps a {@link Goal} with a priority (lower number = more important, Bedrock convention) and running state, tracked
 * by {@link GoalSelector}.
 */
final class PrioritizedGoal{

	private bool $running = false;

	public function __construct(
		public readonly int $priority,
		public readonly Goal $goal
	){}

	public function isRunning() : bool{
		return $this->running;
	}

	public function getFlags() : int{
		return $this->goal->getFlags();
	}

	public function start(MobContext $mob) : void{
		if(!$this->running){
			$this->running = true;
			$this->goal->start($mob);
		}
	}

	public function stop(MobContext $mob) : void{
		if($this->running){
			$this->running = false;
			$this->goal->stop($mob);
		}
	}

	public function tick(MobContext $mob) : void{
		if($this->running){
			$this->goal->tick($mob);
		}
	}
}
