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
use pocketmine\entity\ai\MovementForce;
use pocketmine\entity\ai\nav\AStarPathFinder;
use pocketmine\entity\ai\nav\PathNavigator;
use pocketmine\entity\ai\target\TargetCandidate;
use function floor;
use function max;

/**
 * Pursues the current ATTACK_TARGET, pathing toward it and striking when in melee reach, respecting an attack cooldown.
 * Movement maths and decisions are pure; world side effects are delivered through {@link MobContext}.
 */
final class MeleeAttackGoal extends BaseGoal{
	private const ATTACK_REACH = 2.0;
	private const ATTACK_COOLDOWN_TICKS = 20;
	private const REPATH_INTERVAL = 10;

	private PathNavigator $navigator;
	private int $repathCountdown = 0;
	private int $attackCooldown = 0;

	public function __construct(
		private AStarPathFinder $pathFinder = new AStarPathFinder()
	){
		$this->navigator = new PathNavigator();
	}

	public function getFlags() : int{
		return GoalFlag::MOVE | GoalFlag::LOOK;
	}

	private function target(MobContext $mob) : ?TargetCandidate{
		$target = $mob->getMemory()->get(MemoryModuleType::ATTACK_TARGET);
		return $target instanceof TargetCandidate && $target->alive ? $target : null;
	}

	public function canUse(MobContext $mob) : bool{
		return $this->target($mob) !== null;
	}

	public function start(MobContext $mob) : void{
		$this->repathCountdown = 0;
	}

	public function stop(MobContext $mob) : void{
		$this->navigator->setPath(null);
	}

	public function tick(MobContext $mob) : void{
		if($this->attackCooldown > 0){
			--$this->attackCooldown;
		}

		$target = $this->target($mob);
		if($target === null){
			return;
		}

		$mob->lookAt($target->position());

		$pos = $mob->getPosition();
		$distanceSq = $target->distanceSquaredTo($pos->x, $pos->y, $pos->z);

		if($distanceSq <= self::ATTACK_REACH ** 2){
			if($this->attackCooldown <= 0){
				$mob->attackEntity($target);
				$this->attackCooldown = self::ATTACK_COOLDOWN_TICKS;
			}
			$this->navigator->setPath(null);
			return;
		}

		if(--$this->repathCountdown <= 0 || $this->navigator->isIdle()){
			$this->repathCountdown = self::REPATH_INTERVAL;
			$path = $this->pathFinder->findPath(
				$mob->getNodeAccess(),
				(int) floor($pos->x), (int) floor($pos->y), (int) floor($pos->z),
				(int) floor($target->x), (int) floor($target->y), (int) floor($target->z)
			);
			$this->navigator->setPath($path);
		}

		$intent = $this->navigator->tick($pos);
		if($intent->needsRepath){
			$this->repathCountdown = 0;
			return;
		}
		if($intent->hasMovement()){
			$targetSpeed = max(0.01, $mob->getMovementSpeed());
			$motion = $mob->getMotion();
			$forward = $motion->x * $intent->dirX + $motion->z * $intent->dirZ;
			$force = MovementForce::accelerationForce($targetSpeed, $forward);
			if($force > 0.0){
				$mob->addMotion($intent->dirX * $force, 0, $intent->dirZ * $force);
			}
		}
		if($intent->jump){
			$mob->jump();
		}
	}
}
