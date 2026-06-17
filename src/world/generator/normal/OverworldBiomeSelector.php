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

namespace pocketmine\world\generator\normal;

use pocketmine\data\bedrock\BiomeIds;
use pocketmine\world\biome\Biome;
use pocketmine\world\generator\biome\BiomeSelector;
use function fmod;

/**
 * The overworld biome selector for the Normal generator. Extracted from an anonymous class so it can be re-created
 * deterministically on the MAIN THREAD (the generator only lives in async workers) - the /locate command rebuilds one
 * from the world seed to evaluate the biome at any column without loading the chunk, exactly as the generator does.
 *
 * Every id returned by {@link self::lookup} MUST be registered in {@link \pocketmine\world\biome\BiomeRegistry} or
 * {@link BiomeSelector::recalculate} throws. The temperature/rainfall fields are smooth Simplex noise centred near 0.5,
 * so the band extremes (very hot/dry, very wet) are rare - that is where the rarer biomes live; the centre stays plains.
 */
final class OverworldBiomeSelector extends BiomeSelector{

	protected function lookup(float $temperature, float $rainfall) : int{
		if($rainfall < 0.25){
			//dry band: oceans (by temperature), then beach/river and the hot-dry savanna/mesa
			if($temperature < 0.15){
				return BiomeIds::FROZEN_OCEAN;
			}elseif($temperature < 0.32){
				return BiomeIds::COLD_OCEAN;
			}elseif($temperature < 0.50){
				return BiomeIds::OCEAN;
			}elseif($temperature < 0.62){
				return BiomeIds::DEEP_OCEAN;
			}elseif($temperature < 0.70){
				return BiomeIds::LUKEWARM_OCEAN;
			}elseif($temperature < 0.76){
				return BiomeIds::WARM_OCEAN;
			}elseif($temperature < 0.82){
				return BiomeIds::BEACH;
			}elseif($temperature < 0.88){
				return BiomeIds::RIVER;
			}elseif($temperature < 0.94){
				return BiomeIds::SAVANNA;
			}else{
				return BiomeIds::MESA;
			}
		}elseif($rainfall < 0.60){
			if($temperature < 0.18){
				return BiomeIds::ICE_PLAINS;
			}elseif($temperature < 0.30){
				return BiomeIds::COLD_TAIGA; //snowy taiga
			}elseif($temperature < 0.72){
				return BiomeIds::PLAINS;
			}elseif($temperature < 0.88){
				return BiomeIds::DESERT;
			}else{
				return BiomeIds::MESA;
			}
		}elseif($rainfall < 0.80){
			if($temperature < 0.18){
				return BiomeIds::COLD_TAIGA;
			}elseif($temperature < 0.35){
				return BiomeIds::TAIGA;
			}elseif($temperature < 0.52){
				return BiomeIds::MEGA_TAIGA;
			}elseif($temperature < 0.70){
				return BiomeIds::FOREST;
			}elseif($temperature < 0.84){
				return BiomeIds::BIRCH_FOREST;
			}else{
				return BiomeIds::JUNGLE; //hot + wet rainforest
			}
		}else{
			if($temperature < 0.18){
				return BiomeIds::EXTREME_HILLS;
			}elseif($temperature < 0.35){
				return BiomeIds::EXTREME_HILLS_EDGE;
			}elseif($temperature < 0.55){
				return BiomeIds::SWAMPLAND; //wet + temperate (swamp huts)
			}elseif($temperature < 0.75){
				return BiomeIds::JUNGLE;
			}else{
				return BiomeIds::MUSHROOM_ISLAND; //hot + very wet, rare
			}
		}
	}

	/**
	 * Resolves the biome at a world column the way the Normal generator's generateBiomes does: a small deterministic
	 * coordinate jitter (so biome borders aren't axis-aligned) feeding {@link BiomeSelector::pickBiome}. Shared by the
	 * generator AND /locate so a located biome/structure lands on the same biome the world actually generates.
	 */
	public function pickBiomeJittered(int $x, int $z, int $worldSeed) : Biome{
		$hash = $x * 2345803 ^ $z * 9236449 ^ $worldSeed;
		$hash *= $hash + 223;
		//the above operations may result in a float; mod it so casting back to int doesn't error on PHP 8.5
		$hash = (int) fmod($hash, 2.0 ** 63);
		$xNoise = $hash >> 20 & 3;
		$zNoise = $hash >> 22 & 3;
		if($xNoise === 3){
			$xNoise = 1;
		}
		if($zNoise === 3){
			$zNoise = 1;
		}

		return $this->pickBiome($x + $xNoise - 1, $z + $zNoise - 1);
	}
}
