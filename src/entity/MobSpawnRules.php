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

use function abs;

/**
 * Pure rules for natural mob spawning, isolated from the live World so they can be unit-tested: the light thresholds for
 * hostile vs passive mobs, the per-area population caps, and pack sizes. {@link NaturalSpawner} resolves the world-facing
 * inputs (light, surface, nearby entity counts) and delegates the decisions here.
 */
final class MobSpawnRules{

	/** Hostile mobs spawn only in darkness (full light at or below this). */
	public const HOSTILE_MAX_LIGHT = 7;
	/** Passive animals spawn only in reasonably lit spots (full light at or above this). */
	public const PASSIVE_MIN_LIGHT = 7;

	/** Maximum nearby hostiles before a new hostile spawn is skipped. Kept low so nights don't flood with monsters. */
	public const HOSTILE_CAP = 3;
	/** Maximum nearby passive animals before a new passive spawn is skipped. */
	public const PASSIVE_CAP = 4;
	/** The Nether's whole roster (hostile + neutral) shares one cap, set a little higher so its caverns feel populated. */
	public const NETHER_MOB_CAP = 5;
	/** Radius (blocks) used when counting nearby mobs against the cap. */
	public const CAP_RADIUS = 24;

	public const MAX_HOSTILE_PACK = 2;
	public const MAX_PASSIVE_PACK = 2;

	/**
	 * Hostiles are throttled harder than animals: only 1 in this many resolved-hostile attempts is allowed through, so
	 * nights stay manageable while daytime animal spawning keeps its (already light-gated) rate.
	 */
	public const HOSTILE_ATTEMPT_DENOM = 2;

	private function __construct(){
		//NOOP
	}

	public static function canHostileSpawnAt(int $lightLevel) : bool{
		return $lightLevel <= self::HOSTILE_MAX_LIGHT;
	}

	public static function canPassiveSpawnAt(int $lightLevel) : bool{
		return $lightLevel >= self::PASSIVE_MIN_LIGHT;
	}

	public static function isUnderCap(int $count, int $cap) : bool{
		return $count < $cap;
	}

	/**
	 * A pack size in the range [1, $maxPack] derived from a random roll.
	 */
	public static function packSize(int $roll, int $maxPack) : int{
		if($maxPack <= 1){
			return 1;
		}
		return 1 + (abs($roll) % $maxPack);
	}
}
