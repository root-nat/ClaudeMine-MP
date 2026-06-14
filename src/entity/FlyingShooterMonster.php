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

namespace pocketmine\entity;

use pocketmine\entity\ai\memory\MemoryModuleType;
use pocketmine\entity\ai\sensor\HurtBySensor;
use pocketmine\entity\ai\sensor\NearestPlayersSensor;
use pocketmine\entity\ai\target\TargetCandidate;
use pocketmine\event\entity\EntityDamageEvent;
use function in_array;
use function sqrt;

/**
 * Base for hovering nether shooters (Blaze, Ghast): no gravity, drifts toward the target in 3 dimensions and fires a
 * volley of projectiles on cooldown. Immune to fire/lava. Flying is driven from entityBaseTick rather than the
 * ground-based goal selector, so these mobs hover and fire instead of trying to path along the floor.
 */
abstract class FlyingShooterMonster extends Monster{

	private int $attackCooldown = 0;

	/** Ticks between volleys. */
	abstract protected function attackCooldownTicks() : int;

	/** Projectiles fired per volley. */
	abstract protected function shotsPerVolley() : int;

	/** The mob drifts toward its target until within this many blocks, then hovers. */
	abstract protected function approachDistance() : float;

	/** Maximum distance at which the mob will open fire. */
	abstract protected function shootRange() : float;

	/** Spawn one projectile aimed at the target. */
	abstract protected function fireProjectile(TargetCandidate $target) : void;

	protected function getInitialGravity() : float{
		return 0.0; //hovers; vertical motion is managed in entityBaseTick
	}

	protected function registerBehaviour() : void{
		$this->addSensor(new NearestPlayersSensor($this->getFollowRange()));
		$this->addSensor(new HurtBySensor());
		//no ground stroll/approach goals - the flying behaviour lives in entityBaseTick
	}

	protected function registerAttackGoals() : void{
		//unused: handled by the flying behaviour in entityBaseTick
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->closed || !$this->isAlive()){
			return $hasUpdate;
		}

		if($this->attackCooldown > 0){
			$this->attackCooldown -= $tickDiff;
		}

		$target = $this->getMemory()->get(MemoryModuleType::ATTACK_TARGET);
		if($target instanceof TargetCandidate && $target->alive){
			$this->lookAt($target->position());

			$pos = $this->location;
			$dx = $target->x - $pos->x;
			$dy = ($target->y + 1.0) - $pos->y;
			$dz = $target->z - $pos->z;
			$dist = sqrt(($dx * $dx) + ($dy * $dy) + ($dz * $dz));
			if($dist > $this->approachDistance() && $dist > 1e-4){
				$this->motion = $this->motion->add(($dx / $dist) * 0.04, ($dy / $dist) * 0.04, ($dz / $dist) * 0.04);
			}
			//damp so it glides to a hover instead of accelerating forever
			$this->motion = $this->motion->multiply(0.91);

			if($dist <= $this->shootRange() && $this->attackCooldown <= 0){
				for($i = 0; $i < $this->shotsPerVolley(); ++$i){
					$this->fireProjectile($target);
				}
				$this->attackCooldown = $this->attackCooldownTicks();
			}
		}else{
			$this->motion = $this->motion->multiply(0.85); //no target: settle into a hover
		}

		return true;
	}

	public function attack(EntityDamageEvent $source) : void{
		if(in_array($source->getCause(), [EntityDamageEvent::CAUSE_FIRE, EntityDamageEvent::CAUSE_FIRE_TICK, EntityDamageEvent::CAUSE_LAVA], true)){
			$source->cancel();
		}
		parent::attack($source);
	}
}
