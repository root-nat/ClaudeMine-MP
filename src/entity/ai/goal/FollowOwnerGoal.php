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

/**
 * Makes a tamed mob trot after its owner (recorded in OWNER by {@link \pocketmine\entity\ai\sensor\OwnerSensor}), like a
 * vanilla wolf following the player who tamed it.
 */
final class FollowOwnerGoal extends ApproachMemoryTargetGoal{

	protected function memoryType() : MemoryModuleType{
		return MemoryModuleType::OWNER;
	}

	protected function stopDistance() : float{
		return 2.5;
	}

	protected function speedFactor() : float{
		return 1.2;
	}
}
