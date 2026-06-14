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
use pocketmine\entity\ai\target\TargetCandidate;
use function floor;
use function max;
use function sqrt;

/**
 * Shared behaviour for goals that make a mob run away from a threat: it picks a walkable cell directly away from the
 * threat and sprints there. Subclasses only decide what the threat is (the entity that hurt it, a feared mob nearby...).
 * High priority so fleeing overrides idle behaviour. Pure: flee-vector maths and pathing through {@link MobContext} and
 * its {@link \pocketmine\entity\ai\nav\NodeAccess}.
 */
abstract class FleeFromTargetGoal extends BaseGoal{

	protected PathNavigator $navigator;
	private NodeEvaluator $evaluator;
	private bool $fleeing = false;

	public function __construct(
		private AStarPathFinder $pathFinder = new AStarPathFinder()
	){
		$this->navigator = new PathNavigator();
		$this->evaluator = new NodeEvaluator();
	}

	public function getFlags() : int{
		return GoalFlag::MOVE;
	}

	/**
	 * The thing this mob should flee from this tick, or null if there is nothing to flee.
	 */
	abstract protected function threat(MobContext $mob) : ?TargetCandidate;

	/**
	 * How far (in blocks) the mob tries to put between itself and the threat.
	 */
	protected function fleeDistance() : int{
		return 7;
	}

	/**
	 * Multiplier applied to walk speed while fleeing.
	 */
	protected function speedFactor() : float{
		return 1.5;
	}

	public function canUse(MobContext $mob) : bool{
		$threat = $this->threat($mob);
		if($threat === null){
			return false;
		}
		return $this->chooseFleeCell($mob, $threat) !== null;
	}

	public function canContinueToUse(MobContext $mob) : bool{
		return $this->fleeing && !$this->navigator->isIdle();
	}

	public function start(MobContext $mob) : void{
		$threat = $this->threat($mob);
		if($threat === null){
			return;
		}
		$cell = $this->chooseFleeCell($mob, $threat);
		if($cell === null){
			return;
		}
		$pos = $mob->getPosition();
		$path = $this->pathFinder->findPath(
			$mob->getNodeAccess(),
			(int) floor($pos->x), (int) floor($pos->y), (int) floor($pos->z),
			$cell[0], $cell[1], $cell[2]
		);
		$this->navigator->setPath($path);
		$this->fleeing = $path !== null;
	}

	public function stop(MobContext $mob) : void{
		$this->fleeing = false;
		$this->navigator->setPath(null);
	}

	public function tick(MobContext $mob) : void{
		$intent = $this->navigator->tick($mob->getPosition());
		if($intent->hasMovement()){
			$targetSpeed = max(0.01, $mob->getMovementSpeed()) * $this->speedFactor();
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
	private function chooseFleeCell(MobContext $mob, TargetCandidate $threat) : ?array{
		$pos = $mob->getPosition();
		$dx = $pos->x - $threat->x;
		$dz = $pos->z - $threat->z;
		$length = sqrt(($dx * $dx) + ($dz * $dz));
		if($length < 1e-4){
			$dx = 1.0;
			$dz = 0.0;
			$length = 1.0;
		}
		$awayX = $dx / $length;
		$awayZ = $dz / $length;

		$world = $mob->getNodeAccess();
		$baseY = (int) floor($pos->y);
		for($distance = $this->fleeDistance(); $distance >= 2; --$distance){
			$x = (int) floor($pos->x + $awayX * $distance);
			$z = (int) floor($pos->z + $awayZ * $distance);
			for($dy = 1; $dy >= -2; --$dy){
				$y = $baseY + $dy;
				if($this->evaluator->canStandAt($world, $x, $y, $z)){
					return [$x, $y, $z];
				}
			}
		}
		return null;
	}
}
