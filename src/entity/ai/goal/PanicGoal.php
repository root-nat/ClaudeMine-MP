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

/**
 * Makes a mob flee at increased speed after being hurt: it sprints away from whatever last attacked it (HURT_BY). High
 * priority so panic overrides idle behaviour. Flee mechanics live in {@link FleeFromTargetGoal}.
 */
final class PanicGoal extends FleeFromTargetGoal{

	protected function threat(MobContext $mob) : ?TargetCandidate{
		$threat = $mob->getMemory()->get(MemoryModuleType::HURT_BY);
		return $threat instanceof TargetCandidate ? $threat : null;
	}
}
