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
use function cos;
use function floor;
use function intdiv;
use function max;
use function sin;
use function sqrt;
use const M_PI;

/**
 * Slime/magma-cube locomotion: instead of gliding, the mob springs forward in discrete hops. While grounded and off its
 * hop cooldown it turns to its heading, jumps and gets a horizontal impulse, then sails until it lands. It heads for the
 * current ATTACK_TARGET (hopping more often, and dealing contact damage when it lands on top of the target) or wanders a
 * random heading when idle. Pure: all world/entity effects go through {@link MobContext}, so it is unit-testable.
 */
final class SlimeHopGoal extends BaseGoal{
	private const ATTACK_REACH = 2.0;
	private const ATTACK_COOLDOWN_TICKS = 20;
	private const HEADING_TTL_TICKS = 100; //how long a random idle heading is kept before a new one is rolled
	private const CHASE_INTERVAL_DIVISOR = 3; //hops happen this many times more often while chasing a target

	private int $hopCooldown = 0;
	private int $attackCooldown = 0;
	private int $headingTtl = 0;
	private float $headingX = 0.0;
	private float $headingZ = 0.0;

	/**
	 * @param \Closure $hopParams returns [baseIntervalTicks, jitterTicks, horizontalSpeed] for the slime's CURRENT size.
	 *        It is read live on each hop so a split child (resized after its goals are built) still hops to its own size.
	 * @phpstan-param \Closure() : array{int, int, float} $hopParams
	 */
	public function __construct(
		private \Closure $hopParams
	){}

	public function getFlags() : int{
		return GoalFlag::MOVE | GoalFlag::LOOK;
	}

	public function canUse(MobContext $mob) : bool{
		return true; //a slime is always either hopping toward a target or idly bouncing around
	}

	public function canContinueToUse(MobContext $mob) : bool{
		return true;
	}

	private function target(MobContext $mob) : ?TargetCandidate{
		$target = $mob->getMemory()->get(MemoryModuleType::ATTACK_TARGET);
		return $target instanceof TargetCandidate && $target->alive ? $target : null;
	}

	public function tick(MobContext $mob) : void{
		if($this->attackCooldown > 0){
			--$this->attackCooldown;
		}
		if($this->hopCooldown > 0){
			--$this->hopCooldown;
		}

		$target = $this->target($mob);
		$chasing = $this->updateHeading($mob, $target);

		if($target !== null){
			$pos = $mob->getPosition();
			if($target->distanceSquaredTo($pos->x, $pos->y, $pos->z) <= self::ATTACK_REACH ** 2 && $this->attackCooldown <= 0){
				$mob->attackEntity($target);
				$this->attackCooldown = self::ATTACK_COOLDOWN_TICKS;
			}
		}

		if(!$mob->isOnGround() || $this->hopCooldown > 0){
			return; //mid-leap or still recovering: let physics carry the hop, don't glide
		}

		if($this->headingX === 0.0 && $this->headingZ === 0.0){
			return;
		}

		[$baseInterval, $jitter, $horizontalSpeed] = ($this->hopParams)();

		$mob->setMoveDirection($this->headingX, $this->headingZ);
		$mob->jump();
		$mob->addMotion($this->headingX * $horizontalSpeed, 0, $this->headingZ * $horizontalSpeed);

		$interval = $baseInterval + (int) floor($mob->getRandomFloat() * $jitter);
		$this->hopCooldown = $chasing ? max(2, intdiv($interval, self::CHASE_INTERVAL_DIVISOR)) : $interval;
	}

	/**
	 * Points the heading at the target (and looks at it) when chasing, otherwise rolls/keeps a random wander heading.
	 * Returns true when actively chasing a target.
	 */
	private function updateHeading(MobContext $mob, ?TargetCandidate $target) : bool{
		if($target !== null){
			$pos = $mob->getPosition();
			$dx = $target->x - $pos->x;
			$dz = $target->z - $pos->z;
			$len = sqrt(($dx * $dx) + ($dz * $dz));
			if($len > 1e-4){
				$this->headingX = $dx / $len;
				$this->headingZ = $dz / $len;
			}
			$mob->lookAt($target->position());
			return true;
		}

		if(--$this->headingTtl <= 0 || ($this->headingX === 0.0 && $this->headingZ === 0.0)){
			$angle = $mob->getRandomFloat() * 2 * M_PI;
			$this->headingX = cos($angle);
			$this->headingZ = sin($angle);
			$this->headingTtl = self::HEADING_TTL_TICKS;
		}
		return false;
	}
}
