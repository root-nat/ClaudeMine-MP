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
 * Keeps a preferred distance from the attack target and fires a projectile on cooldown when in range. The actual
 * projectile spawn is delegated to an injected shooter closure so this goal stays free of entity/world coupling and can
 * be unit-tested with a recording closure.
 */
final class RangedAttackGoal extends BaseGoal{

	private PathNavigator $navigator;
	private int $cooldown = 0;
	private int $drawTicks = -1; //-1 = not drawing; otherwise ticks of bow draw remaining before release
	private bool $drawing = false;

	/**
	 * @param \Closure      $shooter         invoked to launch a projectile at the target
	 * @param int           $drawDurationTicks ticks the mob visibly draws/charges before each shot (0 = fire instantly)
	 * @param \Closure|null $onDrawingChange  notified (bool) when the draw pose starts/stops, e.g. to toggle an aim flag
	 * @phpstan-param \Closure(TargetCandidate) : void $shooter
	 * @phpstan-param (\Closure(bool) : void)|null $onDrawingChange
	 */
	public function __construct(
		private \Closure $shooter,
		private float $shootRange = 12.0,
		private float $minRange = 4.0,
		private int $attackIntervalTicks = 30,
		private int $drawDurationTicks = 0,
		private ?\Closure $onDrawingChange = null,
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

	public function stop(MobContext $mob) : void{
		$this->navigator->setPath(null);
		$this->cancelDraw();
	}

	public function tick(MobContext $mob) : void{
		if($this->cooldown > 0){
			--$this->cooldown;
		}

		$target = $this->target($mob);
		if($target === null){
			return;
		}

		$mob->lookAt($target->position());

		$pos = $mob->getPosition();
		$distance = sqrt($target->distanceSquaredTo($pos->x, $pos->y, $pos->z));

		if($distance < $this->minRange){
			//too close: back directly away
			$dx = $pos->x - $target->x;
			$dz = $pos->z - $target->z;
			$length = sqrt(($dx * $dx) + ($dz * $dz));
			if($length > 1e-4){
				$dirX = $dx / $length;
				$dirZ = $dz / $length;
				$motion = $mob->getMotion();
				$forward = $motion->x * $dirX + $motion->z * $dirZ;
				$force = MovementForce::accelerationForce(max(0.01, $mob->getMovementSpeed()), $forward);
				if($force > 0.0){
					$mob->addMotion($dirX * $force, 0, $dirZ * $force);
				}
			}
		}elseif($distance > $this->shootRange){
			//too far: approach via pathfinding
			$path = $this->pathFinder->findPath(
				$mob->getNodeAccess(),
				(int) floor($pos->x), (int) floor($pos->y), (int) floor($pos->z),
				(int) floor($target->x), (int) floor($target->y), (int) floor($target->z)
			);
			$this->navigator->setPath($path);
			$intent = $this->navigator->tick($pos);
			if($intent->hasMovement()){
				$motion = $mob->getMotion();
				$forward = $motion->x * $intent->dirX + $motion->z * $intent->dirZ;
				$force = MovementForce::accelerationForce(max(0.01, $mob->getMovementSpeed()), $forward);
				if($force > 0.0){
					$mob->addMotion($intent->dirX * $force, 0, $intent->dirZ * $force);
				}
			}
			if($intent->jump){
				$mob->jump();
			}
		}

		if($distance <= $this->shootRange){
			//start drawing once off cooldown, then release the arrow when the draw completes (vanilla bow windup)
			if($this->drawTicks < 0 && $this->cooldown <= 0){
				$this->drawTicks = $this->drawDurationTicks;
				$this->setDrawing(true);
			}
			if($this->drawTicks === 0){
				($this->shooter)($target);
				$this->cooldown = $this->attackIntervalTicks;
				$this->drawTicks = -1;
				$this->setDrawing(false);
			}elseif($this->drawTicks > 0){
				--$this->drawTicks;
			}
		}else{
			$this->cancelDraw();
		}
	}

	private function setDrawing(bool $drawing) : void{
		if($drawing === $this->drawing){
			return;
		}
		$this->drawing = $drawing;
		if($this->onDrawingChange !== null){
			($this->onDrawingChange)($drawing);
		}
	}

	private function cancelDraw() : void{
		$this->drawTicks = -1;
		$this->setDrawing(false);
	}
}
