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
 * Keeps a land mob swimming at the water surface instead of sinking and drowning - the engine has no buoyancy, so a mob
 * that walks (or paths) into deep water would otherwise fall to the bottom. While the mob's head is submerged it nudges
 * the vertical velocity up to a gentle swim speed; once the head clears the surface it stops, so the mob bobs at the top
 * and its air refills. Mirrors Bedrock/Java's float goal.
 *
 * It claims only the {@link GoalFlag::JUMP} slot, so it runs CONCURRENTLY with a horizontal MOVE goal: a mob chasing a
 * target across a river swims up while still pathing toward it. Registered for every {@link
 * \pocketmine\entity\ai\AbstractMob}; aquatic mobs are not AbstractMobs, so they are correctly unaffected.
 */
final class FloatGoal extends BaseGoal{

	//Target upward velocity (set at the end of the tick) while submerged. The goal runs AFTER gravity+move in the entity
	//tick, so next tick gravity reduces this to (SWIM_UP_SPEED - gravity) * (1 - drag) ~= (0.12 - 0.08) * 0.98 ~= 0.04
	//block/tick of actual rise; it MUST exceed the mob gravity (0.08) or the mob would still sink. Tuned for a gentle
	//rise; exact Bedrock parity needs in-game calibration.
	private const SWIM_UP_SPEED = 0.12;

	public function getFlags() : int{
		return GoalFlag::JUMP;
	}

	public function canUse(MobContext $mob) : bool{
		return $mob->isUnderwater();
	}

	public function tick(MobContext $mob) : void{
		$motionY = $mob->getMotion()->y;
		if($motionY < self::SWIM_UP_SPEED){
			//target a velocity rather than blindly adding a force: reading the current motion makes the rise robust to the
			//exact gravity/drag the engine applies each tick (which run before this goal in the entity tick).
			$mob->addMotion(0.0, self::SWIM_UP_SPEED - $motionY, 0.0);
		}
	}
}
