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

/**
 * Pure hostile-mob despawn rules, isolated from the live entity so they can be unit-tested. A monster far enough from any
 * player despawns to keep the world from accumulating mobs spawned by {@link NaturalSpawner}: instantly past 128 blocks,
 * and with a small random chance past 32. {@link Monster} resolves the nearest-player distance and applies this.
 */
final class MobDespawnRules{

	public const INSTANT_DESPAWN_DISTANCE = 128;
	public const RANDOM_DESPAWN_DISTANCE = 32;
	/** How often a monster evaluates despawning (ticks). */
	public const CHECK_INTERVAL_TICKS = 20;
	/** Per-check despawn probability denominator past the random distance (≈ vanilla 1/800-per-tick over the interval). */
	public const RANDOM_DESPAWN_PER_CHECK_DENOM = 40;

	private function __construct(){
		//NOOP
	}

	/**
	 * @param float|null $nearestPlayerDistanceSq squared distance to the nearest player, or null if there is none
	 * @param int        $roll                    a random roll in [0, RANDOM_DESPAWN_PER_CHECK_DENOM)
	 */
	public static function shouldDespawn(?float $nearestPlayerDistanceSq, int $roll) : bool{
		if($nearestPlayerDistanceSq === null){
			return true;
		}
		if($nearestPlayerDistanceSq > (self::INSTANT_DESPAWN_DISTANCE ** 2)){
			return true;
		}
		if($nearestPlayerDistanceSq > (self::RANDOM_DESPAWN_DISTANCE ** 2)){
			return $roll === 0;
		}
		return false;
	}
}
