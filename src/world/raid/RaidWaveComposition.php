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

use pocketmine\world\World;
use function array_sum;
use function max;
use function min;

/**
 * Pure data/logic describing how a raid's waves are composed: how many waves a raid has at a given difficulty, the
 * raider counts per type for each wave (mirroring the vanilla per-wave spawn tables), and the extra raiders an ominous
 * (bad-omen-amplified) raid adds. Fully unit-testable; the live spawning/orchestration is a separate world concern.
 */
final class RaidWaveComposition{

	/**
	 * Vanilla spawnsPerWaveBeforeBonus tables, indexed by wave number (1-7; index 0 unused).
	 *
	 * @var array<string, int[]>
	 */
	private const SPAWN_TABLE = [
		"PILLAGER" => [0, 4, 3, 3, 4, 4, 4, 2],
		"VINDICATOR" => [0, 0, 2, 0, 1, 4, 2, 5],
		"WITCH" => [0, 0, 0, 0, 3, 0, 0, 1],
		"RAVAGER" => [0, 0, 0, 1, 0, 1, 0, 2],
		"EVOKER" => [0, 0, 0, 0, 1, 1, 0, 2],
	];

	private function __construct(){
	}

	/**
	 * Returns the number of waves a raid has at the given world difficulty.
	 */
	public static function waveCount(int $difficulty) : int{
		return match($difficulty){
			World::DIFFICULTY_PEACEFUL => 0,
			World::DIFFICULTY_EASY => 3,
			World::DIFFICULTY_NORMAL => 5,
			default => 7, //hard
		};
	}

	/**
	 * Returns whether the given bad-omen level produces an extra (bonus) wave.
	 */
	public static function hasBonusWave(int $badOmenLevel) : bool{
		return $badOmenLevel >= 2;
	}

	/**
	 * Base count of a raider type spawned in the given wave (1-based), before ominous bonuses.
	 */
	public static function baseCount(RaiderType $type, int $wave) : int{
		$table = self::SPAWN_TABLE[$type->name];
		$index = max(0, min($wave, 7));
		return $table[$index];
	}

	/**
	 * Extra raiders of a type added to a wave for an ominous raid. Bad omen level beyond 1 reinforces the tougher
	 * raiders.
	 */
	public static function bonusCount(RaiderType $type, int $badOmenLevel) : int{
		if($badOmenLevel < 2){
			return 0;
		}
		$extra = $badOmenLevel - 1;
		return match($type){
			RaiderType::RAVAGER, RaiderType::EVOKER, RaiderType::VINDICATOR => $extra,
			default => 0,
		};
	}

	/**
	 * Total count of a raider type in a wave, including ominous bonuses (applied to the final wave only).
	 */
	public static function totalCount(RaiderType $type, int $wave, int $difficulty, int $badOmenLevel) : int{
		$count = self::baseCount($type, $wave);
		if($wave >= self::waveCount($difficulty)){
			$count += self::bonusCount($type, $badOmenLevel);
		}
		return $count;
	}

	/**
	 * The full composition of a wave as a raider-type => count map (zero counts omitted).
	 *
	 * @return array<string, int>
	 */
	public static function composition(int $wave, int $difficulty, int $badOmenLevel) : array{
		$result = [];
		foreach(RaiderType::cases() as $type){
			$count = self::totalCount($type, $wave, $difficulty, $badOmenLevel);
			if($count > 0){
				$result[$type->name] = $count;
			}
		}
		return $result;
	}

	/**
	 * Total number of raiders across all waves of a raid.
	 */
	public static function totalRaiders(int $difficulty, int $badOmenLevel) : int{
		$total = 0;
		$waves = self::waveCount($difficulty);
		for($wave = 1; $wave <= $waves; ++$wave){
			$total += array_sum(self::composition($wave, $difficulty, $badOmenLevel));
		}
		return $total;
	}
}
