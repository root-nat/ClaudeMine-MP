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

use pocketmine\entity\ai\AbstractMob;
use pocketmine\entity\ai\goal\RandomStrollGoal;
use pocketmine\entity\ai\sensor\HurtBySensor;
use pocketmine\entity\ai\sensor\NearestPlayersSensor;
use function floor;
use function mt_rand;

/**
 * Base class for hostile monsters. Wires the player-targeting and retaliation sensors plus idle wandering; species
 * declare their own attack goal(s) in registerAttackGoals(). Built on the {@link AbstractMob} AI core. Hostile monsters
 * despawn when far from any player (see {@link MobDespawnRules}) so naturally-spawned mobs don't pile up.
 */
abstract class Monster extends AbstractMob{

	/** Set for mobs that must never be culled by the distance despawn (e.g. raid members), independent of any name tag. */
	private bool $persistent = false;

	protected function registerBehaviour() : void{
		$this->addSensor(new NearestPlayersSensor($this->getFollowRange()));
		$this->addSensor(new HurtBySensor());

		$this->registerAttackGoals();
		$this->addGoal(8, new RandomStrollGoal());
	}

	/**
	 * Declares the monster's combat goal(s) (melee, ranged, swell...). Called before idle goals so they take priority.
	 */
	abstract protected function registerAttackGoals() : void;

	/**
	 * Persistent monsters (named ones, or those explicitly pinned like raid members) never despawn.
	 */
	public function isPersistent() : bool{
		return $this->persistent || $this->getNameTag() !== "";
	}

	/**
	 * Pins this monster against the distance despawn without giving it a name tag (used for raid members).
	 */
	public function setPersistent(bool $persistent = true) : void{
		$this->persistent = $persistent;
	}

	/**
	 * Whether this monster catches fire in direct daylight (zombies, skeletons...). Override to true for undead.
	 */
	protected function burnsInDaylight() : bool{
		return false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->closed || !$this->isAlive()){
			return $hasUpdate;
		}

		if(!$this->isPersistent() && ($this->ticksLived % MobDespawnRules::CHECK_INTERVAL_TICKS) === 0){
			$roll = mt_rand(0, MobDespawnRules::RANDOM_DESPAWN_PER_CHECK_DENOM - 1);
			if(MobDespawnRules::shouldDespawn($this->nearestPlayerDistanceSquared(), $roll)){
				$this->flagForDespawn();
			}
		}

		if($this->burnsInDaylight() && !$this->isOnFire()){
			$this->tickDaylightBurning();
		}

		return $hasUpdate;
	}

	private function tickDaylightBurning() : void{
		$world = $this->getWorld();
		$x = (int) floor($this->location->x);
		$z = (int) floor($this->location->z);
		$highest = $world->getHighestBlockAt($x, $z);
		$skyExposed = $highest !== null && (int) floor($this->location->y) >= $highest;
		if(DaylightBurnRules::shouldBurn($world->getTimeOfDay(), $skyExposed, $this->isUnderwater(), $world->getWeather()->isRaining())){
			$this->setOnFire(8);
		}
	}

	private function nearestPlayerDistanceSquared() : ?float{
		$nearest = null;
		foreach($this->getWorld()->getPlayers() as $player){
			if(!$player->isAlive()){
				continue;
			}
			$distSq = $player->getPosition()->distanceSquared($this->location);
			if($nearest === null || $distSq < $nearest){
				$nearest = $distSq;
			}
		}
		return $nearest;
	}
}
