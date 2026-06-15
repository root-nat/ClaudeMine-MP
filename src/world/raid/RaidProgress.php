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

namespace pocketmine\world\raid;

/**
 * Pure decision logic for advancing a raid: given how many waves have spawned, how many raiders are still alive, and how
 * long the defenders have been absent, it decides whether to wait, spawn the next wave, declare victory, or dissipate.
 * Fully unit-testable; the live spawning and effects are a separate world concern (see Raid).
 */
final class RaidProgress{

	private function __construct(){
	}

	/**
	 * @param int  $spawnedWaves how many waves have already been spawned (0 at raid start)
	 * @param int  $totalWaves   how many waves this raid has in total
	 * @param int  $aliveRaiders how many of the current wave's raiders are still alive
	 * @param int  $idleTicks    how long (in raid ticks) no defender has been near the raid
	 * @param int  $maxIdleTicks  the abandonment threshold after which the raid dissipates
	 * @param bool $hasDefender   whether a defender is currently near the raid
	 * @param bool $villageFallen whether every villager the raid was attacking is now dead
	 */
	public static function decide(int $spawnedWaves, int $totalWaves, int $aliveRaiders, int $idleTicks, int $maxIdleTicks, bool $hasDefender, bool $villageFallen) : RaidAction{
		//the raiders wiped out the village they came for: the defenders lose, regardless of how many raiders remain
		if($villageFallen){
			return RaidAction::DEFEAT;
		}
		//an abandoned raid dissipates, even if raiders are still alive
		if($idleTicks >= $maxIdleTicks){
			return RaidAction::DEFEAT;
		}
		//while the current wave still has living raiders, keep fighting it
		if($aliveRaiders > 0){
			return RaidAction::WAIT;
		}
		//the field is clear and all waves are done: the defenders win (regardless of who is still standing nearby)
		if($spawnedWaves >= $totalWaves){
			return RaidAction::VICTORY;
		}
		//hold the next wave until a defender is present, so a raid never spawns/announces into an unattended village
		if(!$hasDefender){
			return RaidAction::WAIT;
		}
		return RaidAction::SPAWN_WAVE;
	}
}
