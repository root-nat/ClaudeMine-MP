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
use function sqrt;

/**
 * Shared base for goals that make a mob look at and walk toward a {@link TargetCandidate} stored in a memory slot (the
 * tempting player, a breeding mate, a parent...). It paths via {@link PathNavigator} and, when no path is usable (target
 * unreachable, stuck, or path finished short), falls back to moving straight toward the target so the mob never freezes
 * in place. Subclasses pick the memory slot, stop distance and speed. Pure: all world effects go through {@link MobContext}.
 */
abstract class ApproachMemoryTargetGoal extends BaseGoal{
	protected const REPATH_INTERVAL = 10;

	private PathNavigator $navigator;
	private int $repathCountdown = 0;

	public function __construct(
		private AStarPathFinder $pathFinder = new AStarPathFinder()
	){
		$this->navigator = new PathNavigator();
	}

	abstract protected function memoryType() : MemoryModuleType;

	protected function stopDistance() : float{
		return 2.5;
	}

	protected function speedFactor() : float{
		return 1.0;
	}

	public function getFlags() : int{
		return GoalFlag::MOVE | GoalFlag::LOOK;
	}

	protected function target(MobContext $mob) : ?TargetCandidate{
		$target = $mob->getMemory()->get($this->memoryType());
		return $target instanceof TargetCandidate && $target->alive ? $target : null;
	}

	public function canUse(MobContext $mob) : bool{
		return $this->target($mob) !== null;
	}

	public function stop(MobContext $mob) : void{
		$this->navigator->setPath(null);
	}

	/**
	 * Called each tick while the mob stands within stopDistance of its target. Subclasses override to act on arrival (a
	 * piglin grabbing the gold it walked to...); the default does nothing, so plain approach goals just stop and wait.
	 */
	protected function onReachedTarget(MobContext $mob, TargetCandidate $target) : void{
	}

	public function tick(MobContext $mob) : void{
		$target = $this->target($mob);
		if($target === null){
			return;
		}

		$mob->lookAt($target->position());

		$pos = $mob->getPosition();
		if($target->distanceSquaredTo($pos->x, $pos->y, $pos->z) <= $this->stopDistance() ** 2){
			$this->navigator->setPath(null);
			$this->onReachedTarget($mob, $target);
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
		}

		if($intent->hasMovement()){
			$this->moveToward($mob, $intent->dirX, $intent->dirZ);
			if($intent->jump){
				$mob->jump();
			}
			return;
		}

		//no usable path this tick: approach directly so the mob doesn't freeze. The 1-block step height handles small obstacles.
		$dx = $target->x - $pos->x;
		$dz = $target->z - $pos->z;
		$length = sqrt(($dx * $dx) + ($dz * $dz));
		if($length > 1e-4){
			$this->moveToward($mob, $dx / $length, $dz / $length);
		}
	}

	private function moveToward(MobContext $mob, float $dirX, float $dirZ) : void{
		$targetSpeed = max(0.01, $mob->getMovementSpeed()) * $this->speedFactor();
		$motion = $mob->getMotion();
		$forward = $motion->x * $dirX + $motion->z * $dirZ;
		$force = MovementForce::accelerationForce($targetSpeed, $forward);
		if($force > 0.0){
			$mob->addMotion($dirX * $force, 0, $dirZ * $force);
		}
		$mob->setMoveDirection($dirX, $dirZ);
	}
}
