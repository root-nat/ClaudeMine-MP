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
use function sin;
use function sqrt;
use const M_PI;

/**
 * Rabbit locomotion: rabbits don't glide, they spring forward in discrete hops. Registered at top priority so it owns all
 * of the rabbit's movement, reading the memory slots the standard sensors already fill to pick a heading each hop, by
 * priority: flee a threat it is afraid of or that just hurt it (faster hops, away), else hop toward a mate or a player
 * tempting it with food, else wander a random heading with the odd pause. Pure: all effects go through {@link MobContext}.
 */
final class RabbitHopGoal extends BaseGoal{
	private const HEADING_TTL_TICKS = 50; //how long an idle wander heading (or pause) is kept before re-rolling
	private const HOP_INTERVAL = 10;
	private const HOP_JITTER = 8;
	private const FLEE_HOP_INTERVAL = 5;
	private const HOP_SPEED = 0.3;
	private const FLEE_HOP_SPEED = 0.5;
	private const PAUSE_CHANCE = 0.35; //chance an idle re-roll is a stand-still instead of a new heading

	private int $hopCooldown = 0;
	private int $headingTtl = 0;
	private float $headingX = 0.0;
	private float $headingZ = 0.0;

	public function getFlags() : int{
		return GoalFlag::MOVE | GoalFlag::LOOK;
	}

	public function canUse(MobContext $mob) : bool{
		return true; //a rabbit is always either fleeing, hopping somewhere it wants to be, or idly bouncing
	}

	public function canContinueToUse(MobContext $mob) : bool{
		return true;
	}

	private function memory(MobContext $mob, MemoryModuleType $type) : ?TargetCandidate{
		$target = $mob->getMemory()->get($type);
		return $target instanceof TargetCandidate && $target->alive ? $target : null;
	}

	public function tick(MobContext $mob) : void{
		if($this->hopCooldown > 0){
			--$this->hopCooldown;
		}

		$threat = $this->memory($mob, MemoryModuleType::AVOID_TARGET) ?? $this->memory($mob, MemoryModuleType::HURT_BY);
		$fleeing = $threat !== null;
		if($fleeing){
			$this->headAwayFrom($mob, $threat);
		}else{
			$attract = $this->memory($mob, MemoryModuleType::BREED_TARGET)
				?? $this->memory($mob, MemoryModuleType::PARENT)
				?? $this->memory($mob, MemoryModuleType::TEMPTING_PLAYER);
			if($attract !== null){
				$this->headTowards($mob, $attract);
				$mob->lookAt($attract->position());
			}else{
				$this->wander($mob);
			}
		}

		if(!$mob->isOnGround() || $this->hopCooldown > 0){
			return; //mid-leap or recovering: let physics carry the hop, don't glide
		}
		if($this->headingX === 0.0 && $this->headingZ === 0.0){
			return; //pausing
		}

		$speed = $fleeing ? self::FLEE_HOP_SPEED : self::HOP_SPEED;
		$mob->setMoveDirection($this->headingX, $this->headingZ);
		$mob->jump();
		$mob->addMotion($this->headingX * $speed, 0, $this->headingZ * $speed);

		$this->hopCooldown = $fleeing ? self::FLEE_HOP_INTERVAL : (self::HOP_INTERVAL + (int) floor($mob->getRandomFloat() * self::HOP_JITTER));
	}

	private function headAwayFrom(MobContext $mob, TargetCandidate $threat) : void{
		$pos = $mob->getPosition();
		$this->setHeading($pos->x - $threat->x, $pos->z - $threat->z);
		$this->headingTtl = 0;
	}

	private function headTowards(MobContext $mob, TargetCandidate $target) : void{
		$pos = $mob->getPosition();
		$this->setHeading($target->x - $pos->x, $target->z - $pos->z);
		$this->headingTtl = 0;
	}

	private function wander(MobContext $mob) : void{
		if(--$this->headingTtl > 0){
			return; //keep the current wander heading (or pause) for a while
		}
		$this->headingTtl = self::HEADING_TTL_TICKS;
		if($mob->getRandomFloat() < self::PAUSE_CHANCE){
			$this->headingX = 0.0;
			$this->headingZ = 0.0;
			return;
		}
		$angle = $mob->getRandomFloat() * 2 * M_PI;
		$this->headingX = cos($angle);
		$this->headingZ = sin($angle);
	}

	private function setHeading(float $dx, float $dz) : void{
		$len = sqrt(($dx * $dx) + ($dz * $dz));
		if($len > 1e-4){
			$this->headingX = $dx / $len;
			$this->headingZ = $dz / $len;
		}
	}
}
