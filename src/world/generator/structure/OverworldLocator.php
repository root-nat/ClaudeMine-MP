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

namespace pocketmine\world\generator\structure;

use Closure;
use pocketmine\world\format\Chunk;
use function in_array;
use function sqrt;
use const PHP_INT_MAX;

/**
 * Pure, seed-based locator for the /locate command. Finds the nearest overworld structure or biome to an origin WITHOUT
 * loading chunks: structures are re-derived from the same deterministic anchor draw the generator uses ({@link
 * SurfaceStructurePopulator::originAt}), and biomes are evaluated by a caller-supplied closure (the command feeds it a
 * seed-rebuilt OverworldBiomeSelector). It searches outward ring by ring around the origin chunk and stops as soon as no
 * unscanned ring could hold a closer match, so it returns the true nearest. No engine/World dependency = unit-testable.
 */
final class OverworldLocator{

	private function __construct(){
		//NOOP
	}

	/**
	 * @param int[]                  $biomeIds  biome ids the structure may anchor in
	 * @param Closure(int, int) : int $biomeIdAt resolves the biome id at a block column (x, z)
	 *
	 * @return array{int, int, int}|null [anchorX, anchorZ, distanceBlocks] of the nearest structure, or null if none within range
	 */
	public static function nearestStructure(int $worldSeed, int $salt, int $rarity, array $biomeIds, int $originX, int $originZ, int $maxChunkRadius, Closure $biomeIdAt) : ?array{
		$originCX = $originX >> Chunk::COORD_BIT_SIZE;
		$originCZ = $originZ >> Chunk::COORD_BIT_SIZE;
		$bestX = null;
		$bestZ = 0;
		$bestDist2 = PHP_INT_MAX;

		for($d = 0; $d <= $maxChunkRadius; ++$d){
			if($bestX !== null && self::ringMinDistance2($d) > $bestDist2){
				break; //no chunk in this or any farther ring can be closer than the best found
			}
			foreach(self::ringCoords($originCX, $originCZ, $d) as [$rx, $rz]){
				$origin = SurfaceStructurePopulator::originAt($worldSeed, $rx, $rz, $salt, $rarity);
				if($origin === null){
					continue;
				}
				[$ax, $az] = $origin;
				//an empty allow-list means "any biome" (matches the populator's gate)
				if($biomeIds !== [] && !in_array($biomeIdAt($ax, $az), $biomeIds, true)){
					continue;
				}
				$dist2 = ($ax - $originX) ** 2 + ($az - $originZ) ** 2;
				if($dist2 < $bestDist2){
					$bestDist2 = $dist2;
					$bestX = $ax;
					$bestZ = $az;
				}
			}
		}
		return $bestX === null ? null : [$bestX, $bestZ, (int) sqrt($bestDist2)];
	}

	/**
	 * @param Closure(int, int) : int $biomeIdAt resolves the biome id at a block column (x, z)
	 *
	 * @return array{int, int, int}|null [x, z, distanceBlocks] of the nearest matching biome (sampled at chunk centres), or null
	 */
	public static function nearestBiome(int $targetBiomeId, int $originX, int $originZ, int $maxChunkRadius, Closure $biomeIdAt) : ?array{
		$originCX = $originX >> Chunk::COORD_BIT_SIZE;
		$originCZ = $originZ >> Chunk::COORD_BIT_SIZE;
		$bestX = null;
		$bestZ = 0;
		$bestDist2 = PHP_INT_MAX;

		for($d = 0; $d <= $maxChunkRadius; ++$d){
			if($bestX !== null && self::ringMinDistance2($d) > $bestDist2){
				break;
			}
			foreach(self::ringCoords($originCX, $originCZ, $d) as [$rx, $rz]){
				$sx = ($rx << Chunk::COORD_BIT_SIZE) + 8; //chunk centre, 16-block sampling resolution
				$sz = ($rz << Chunk::COORD_BIT_SIZE) + 8;
				if($biomeIdAt($sx, $sz) !== $targetBiomeId){
					continue;
				}
				$dist2 = ($sx - $originX) ** 2 + ($sz - $originZ) ** 2;
				if($dist2 < $bestDist2){
					$bestDist2 = $dist2;
					$bestX = $sx;
					$bestZ = $sz;
				}
			}
		}
		return $bestX === null ? null : [$bestX, $bestZ, (int) sqrt($bestDist2)];
	}

	/** The minimum possible squared block distance from the origin to any chunk on the chebyshev ring at distance $d. */
	private static function ringMinDistance2(int $d) : int{
		$gap = ($d - 1) * Chunk::EDGE_LENGTH; //(d-1) full chunks separate the origin chunk from a ring-d chunk
		return $gap <= 0 ? 0 : $gap ** 2;
	}

	/**
	 * The chunk coordinates forming the chebyshev ring at distance $d around (cx, cz).
	 *
	 * @return list<array{int, int}>
	 */
	private static function ringCoords(int $cx, int $cz, int $d) : array{
		if($d === 0){
			return [[$cx, $cz]];
		}
		$coords = [];
		for($i = -$d; $i <= $d; ++$i){
			$coords[] = [$cx + $i, $cz - $d];
			$coords[] = [$cx + $i, $cz + $d];
		}
		for($j = -$d + 1; $j <= $d - 1; ++$j){
			$coords[] = [$cx - $d, $cz + $j];
			$coords[] = [$cx + $d, $cz + $j];
		}
		return $coords;
	}
}
