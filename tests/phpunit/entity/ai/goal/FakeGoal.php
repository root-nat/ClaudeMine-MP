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
 * Spy {@link Goal} for GoalSelector tests: toggleable usability and recorded start/stop/tick counts.
 */
final class FakeGoal extends BaseGoal{

	public bool $usable = true;
	public int $startCount = 0;
	public int $stopCount = 0;
	public int $tickCount = 0;
	public bool $running = false;

	public function __construct(private int $flags){
	}

	public function getFlags() : int{
		return $this->flags;
	}

	public function canUse(MobContext $mob) : bool{
		return $this->usable;
	}

	public function start(MobContext $mob) : void{
		$this->running = true;
		++$this->startCount;
	}

	public function stop(MobContext $mob) : void{
		$this->running = false;
		++$this->stopCount;
	}

	public function tick(MobContext $mob) : void{
		++$this->tickCount;
	}
}
