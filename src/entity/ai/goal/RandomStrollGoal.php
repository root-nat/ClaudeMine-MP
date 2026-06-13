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
use pocketmine\entity\ai\MovementForce;
use pocketmine\entity\ai\nav\AStarPathFinder;
use pocketmine\entity\ai\nav\NodeEvaluator;
use pocketmine\entity\ai\nav\PathNavigator;
use function floor;
use function max;

/**
 * Idle wandering: occasionally picks a random nearby walkable cell and strolls to it at a reduced speed. Lowest-priority
 * movement goal, preempted by anything that also needs the MOVE flag. Destination selection and movement are pure
 * (driven through {@link MobContext} and its {@link NodeAccess}).
 */
final class RandomStrollGoal extends BaseGoal{
	private const STROLL_CHANCE = 0.008; //~1 in 120 ticks
	private const RADIUS = 8;
	private const ATTEMPTS = 6;
	private const SPEED_FACTOR = 0.6;

	private PathNavigator $navigator;
	private NodeEvaluator $evaluator;

	public function __construct(
		private AStarPathFinder $pathFinder = new AStarPathFinder()
	){
		$this->navigator = new PathNavigator();
		$this->evaluator = new NodeEvaluator();
	}

	public function getFlags() : int{
		return GoalFlag::MOVE;
	}

	public function canUse(MobContext $mob) : bool{
		if($mob->getRandomFloat() > self::STROLL_CHANCE){
			return false;
		}
		return $this->chooseDestination($mob) !== null;
	}

	public function canContinueToUse(MobContext $mob) : bool{
		return !$this->navigator->isIdle();
	}

	public function start(MobContext $mob) : void{
		$dest = $this->chooseDestination($mob);
		if($dest === null){
			return;
		}
		$pos = $mob->getPosition();
		$path = $this->pathFinder->findPath(
			$mob->getNodeAccess(),
			(int) floor($pos->x), (int) floor($pos->y), (int) floor($pos->z),
			$dest[0], $dest[1], $dest[2]
		);
		$this->navigator->setPath($path);
	}

	public function stop(MobContext $mob) : void{
		$this->navigator->setPath(null);
	}

	public function tick(MobContext $mob) : void{
		$pos = $mob->getPosition();
		$intent = $this->navigator->tick($pos);
		if($intent->hasMovement()){
			$targetSpeed = max(0.01, $mob->getMovementSpeed()) * self::SPEED_FACTOR;
			$motion = $mob->getMotion();
			$forward = $motion->x * $intent->dirX + $motion->z * $intent->dirZ;
			$force = MovementForce::accelerationForce($targetSpeed, $forward);
			if($force > 0.0){
				$mob->addMotion($intent->dirX * $force, 0, $intent->dirZ * $force);
			}
			$mob->setMoveDirection($intent->dirX, $intent->dirZ);
		}
		if($intent->jump){
			$mob->jump();
		}
	}

	/**
	 * @return int[]|null
	 * @phpstan-return array{int, int, int}|null
	 */
	private function chooseDestination(MobContext $mob) : ?array{
		$world = $mob->getNodeAccess();
		$pos = $mob->getPosition();
		$baseX = (int) floor($pos->x);
		$baseY = (int) floor($pos->y);
		$baseZ = (int) floor($pos->z);

		for($i = 0; $i < self::ATTEMPTS; ++$i){
			$dx = (int) floor(($mob->getRandomFloat() * 2 - 1) * self::RADIUS);
			$dz = (int) floor(($mob->getRandomFloat() * 2 - 1) * self::RADIUS);
			if($dx === 0 && $dz === 0){
				continue;
			}
			$x = $baseX + $dx;
			$z = $baseZ + $dz;
			//snap onto the local surface within +/-2 of the mob's feet
			for($dy = 2; $dy >= -2; --$dy){
				$y = $baseY + $dy;
				if($this->evaluator->canStandAt($world, $x, $y, $z)){
					return [$x, $y, $z];
				}
			}
		}
		return null;
	}
}
