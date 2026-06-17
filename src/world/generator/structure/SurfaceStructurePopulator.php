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
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\carver\ChunkManagerVolume;
use pocketmine\world\generator\carver\RegionRandom;
use pocketmine\world\generator\populator\Populator;
use function in_array;
use function intdiv;

/**
 * Places a surface structure (e.g. a desert temple) after terrain generation. Like {@link NetherBastionPopulator} the
 * per-region anchor derivation lives in a shared static {@link self::forEachOrigin} that the main-thread
 * {@link OverworldStructureFurnisher} re-runs with the same SALT/rarity, so loot chests and mobs land exactly where the
 * blocks were placed. Unlike the cave dungeon (which scans for an air-over-solid cave floor) this anchors on the SURFACE:
 * the structure's reference column is the topmost solid ground block found by {@link SurfaceScan::topSolidY}.
 *
 * Determinism: a surface structure must NEVER build over its own anchor column, so the surface scan at that column yields
 * the same ground Y from every chunk that re-derives the structure (and again, identically, from the furnisher) - the
 * structure is built at fixed offsets around the anchor. The structure is also biome-gated: it places only where the
 * anchor column's biome id is in the allow-list.
 */
final class SurfaceStructurePopulator implements Populator{

	/**
	 * @param int[] $biomeAllow biome ids (pocketmine\data\bedrock\BiomeIds) this structure may anchor in
	 */
	public function __construct(
		private int $worldSeed,
		private Structure $structure,
		private int $salt,
		private int $rarity,
		private array $biomeAllow,
		private int $maxRadius,
		private int $surfaceTopY = 128,
		private int $surfaceMinY = 4
	){}

	public static function scanRadius(int $maxRadius) : int{
		return intdiv($maxRadius + Chunk::EDGE_LENGTH - 1, Chunk::EDGE_LENGTH);
	}

	/**
	 * Invokes $callback($anchorX, $anchorZ, $regionRandom) once per structure origin whose footprint can reach the given
	 * chunk. Deterministic for a world seed; SHARED by the async populator and the main-thread furnisher so both agree on
	 * every structure's anchor column. NOTE: the anchor Y is NOT emitted here - it is a terrain function resolved by an
	 * identical {@link SurfaceScan} on each side (the anchor column is never built over, so the scan is stable).
	 *
	 * @phpstan-param Closure(int, int, Random) : void $callback
	 */
	public static function forEachOrigin(int $worldSeed, int $chunkX, int $chunkZ, int $salt, int $rarity, int $maxRadius, Closure $callback) : void{
		$scanRadius = self::scanRadius($maxRadius);
		for($rx = $chunkX - $scanRadius; $rx <= $chunkX + $scanRadius; ++$rx){
			for($rz = $chunkZ - $scanRadius; $rz <= $chunkZ + $scanRadius; ++$rz){
				$origin = self::originAt($worldSeed, $rx, $rz, $salt, $rarity);
				if($origin !== null){
					[$ax, $az, $random] = $origin;
					$callback($ax, $az, $random);
				}
			}
		}
	}

	/**
	 * The single source of truth for a structure's per-region anchor draw: derives the region Random, rolls rarity, and
	 * draws the anchor (ax, az). Returns [anchorX, anchorZ, regionRandom] or null if the rarity roll fails. Shared by
	 * {@link self::forEachOrigin} (placement + furnishing) AND the /locate command (so the located position is exactly
	 * where the structure generates). Deterministic for a world seed.
	 *
	 * @return array{int, int, Random}|null
	 */
	public static function originAt(int $worldSeed, int $regionX, int $regionZ, int $salt, int $rarity) : ?array{
		$random = RegionRandom::derive($worldSeed, $regionX, $regionZ, $salt);
		if($rarity > 1 && $random->nextBoundedInt($rarity) !== 0){
			return null;
		}
		$ax = $regionX * Chunk::EDGE_LENGTH + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
		$az = $regionZ * Chunk::EDGE_LENGTH + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
		return [$ax, $az, $random];
	}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$volume = new ChunkManagerVolume($world);
		self::forEachOrigin($this->worldSeed, $chunkX, $chunkZ, $this->salt, $this->rarity, $this->maxRadius, function(int $ax, int $az, Random $regionRandom) use ($world, $volume) : void{
			$groundY = SurfaceScan::topSolidY(fn(int $y) : int => $volume->getBlockStateId($ax, $y, $az), $this->surfaceTopY, $this->surfaceMinY);
			if($groundY === null){
				return; //column not loaded in this populate pass, or no ground - owned by another pass
			}
			$chunk = $world->getChunk($ax >> Chunk::COORD_BIT_SIZE, $az >> Chunk::COORD_BIT_SIZE);
			if($chunk === null){
				return; //origin column not loaded in this pass
			}
			//an empty allow-list means "any biome" (e.g. underground geodes); otherwise gate on the anchor column's biome
			if($this->biomeAllow !== [] && !in_array($chunk->getBiomeId($ax & 0x0f, $groundY, $az & 0x0f), $this->biomeAllow, true)){
				return; //wrong biome at the anchor column
			}
			if($this->structure->canPlace($volume, $ax, $groundY, $az)){
				$this->structure->place($volume, $ax, $groundY, $az, $regionRandom);
			}
		});
	}
}
