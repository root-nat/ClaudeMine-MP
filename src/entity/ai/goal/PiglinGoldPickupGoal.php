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
use pocketmine\entity\Piglin;

/**
 * Walks a piglin to the nearest dropped gold ingot (PICKUP_TARGET) and, on arrival, has it grab the gold and start
 * bartering - the "throw gold to a piglin" trade. Navigation and arrival detection come from
 * {@link ApproachMemoryTargetGoal}; this only fills in the memory slot and the on-arrival pickup.
 */
final class PiglinGoldPickupGoal extends ApproachMemoryTargetGoal{

	protected function memoryType() : MemoryModuleType{
		return MemoryModuleType::PICKUP_TARGET;
	}

	protected function speedFactor() : float{
		return 1.1; //eager for gold
	}

	protected function stopDistance() : float{
		return 1.0; //must be right on top of the item to grab it
	}

	protected function onReachedTarget(MobContext $mob, TargetCandidate $target) : void{
		if($mob instanceof Piglin && $mob->pickUpBarterGold($target->entityId)){
			$mob->getMemory()->erase(MemoryModuleType::PICKUP_TARGET);
		}
	}
}
