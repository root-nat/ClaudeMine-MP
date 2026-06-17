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
use pocketmine\world\generator\carver\GenerationVolume;
use pocketmine\world\generator\carver\RegionRandom;
use pocketmine\world\generator\populator\Populator;
use function max;
use function min;

/**
 * Places dungeons into caves after terrain and carving are complete. The placement decision/geometry is pure logic over
 * a {@link GenerationVolume} ({@link StructurePopulator::tryPlace}), so it is unit-tested against an in-memory volume;
 * {@link StructurePopulator::populate} is the thin live-ChunkManager bridge registered in the generator.
 */
final class StructurePopulator implements Populator{

	private const ANCHOR_ATTEMPTS = 6;

	/** Per-region seed salt for the dungeon anchor draw. Shared by {@link self::forEachRegionCandidate} so the populator
	 * and the main-thread {@link DungeonFurnisher} derive the same candidate columns. */
	public const SALT = 0x6a17;

	//placement parameters: the generator registration (Normal) and the DungeonFurnisher MUST agree on these, so they live
	//here as the single source of truth and double as the constructor defaults.
	public const DEFAULT_RARITY = 8;
	public const DEFAULT_MIN_ANCHOR_Y = -54;
	public const DEFAULT_MAX_ANCHOR_Y = 46;

	public function __construct(
		private int $worldSeed,
		private DungeonStructure $dungeon,
		private int $rarity = self::DEFAULT_RARITY,
		private int $minAnchorY = self::DEFAULT_MIN_ANCHOR_Y,
		private int $maxAnchorY = self::DEFAULT_MAX_ANCHOR_Y,
		private int $airStateId = 0
	){}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$this->tryPlace(new ChunkManagerVolume($world), $chunkX, $chunkZ);
	}

	/**
	 * Attempts to place dungeons whose origin region overlaps the given chunk. Every chunk a dungeon's geometry touches
	 * re-derives that dungeon identically from its originating region's seed (matching the carvers), so a dungeon that
	 * spills across a chunk boundary is reproduced rather than half-generated. Returns true if at least one dungeon was
	 * placed. Deterministic for a given world seed.
	 */
	public function tryPlace(GenerationVolume $volume, int $chunkX, int $chunkZ) : bool{
		$placed = false;
		self::forEachRegionCandidate($this->worldSeed, $chunkX, $chunkZ, $this->rarity, $this->minAnchorY, $this->maxAnchorY, $volume->getMinY(), $volume->getMaxY(),
			function(int $x, int $z, int $startY, int $lowY, int $highY, Random $random) use ($volume, &$placed) : bool{
				$anchorY = $this->findCaveFloor($volume, $x, $startY, $z, $lowY, $highY);
				if($anchorY !== null && $this->dungeon->canPlace($volume, $x, $anchorY, $z)){
					$this->dungeon->place($volume, $x, $anchorY, $z, $random);
					$placed = true;
					return true; //placed - stop this region's attempts
				}
				return false;
			});
		return $placed;
	}

	/**
	 * Replays the deterministic per-region dungeon anchor draw shared by the async populator (placement) and the
	 * main-thread {@link DungeonFurnisher} (loot). For every region whose footprint can reach (chunkX, chunkZ) it derives
	 * the region Random, rolls rarity, then draws up to {@link self::ANCHOR_ATTEMPTS} candidate (x, z, startY) anchors and
	 * invokes $attempt for each; $attempt returns true to stop this region (i.e. the dungeon was placed/found here),
	 * mirroring how placement stops at the first viable cave floor. Deterministic for a world seed; the lowY/highY window
	 * is a function of the same min/max anchor Y and the volume/world vertical bounds on both sides.
	 *
	 * @phpstan-param Closure(int $x, int $z, int $startY, int $lowY, int $highY, Random $random) : bool $attempt
	 */
	public static function forEachRegionCandidate(int $worldSeed, int $chunkX, int $chunkZ, int $rarity, int $minAnchorY, int $maxAnchorY, int $boundsMinY, int $boundsMaxY, Closure $attempt) : void{
		//a dungeon anchored in any of the 8 neighbouring regions may reach into this chunk
		for($rx = $chunkX - 1; $rx <= $chunkX + 1; ++$rx){
			for($rz = $chunkZ - 1; $rz <= $chunkZ + 1; ++$rz){
				$random = RegionRandom::derive($worldSeed, $rx, $rz, self::SALT);
				if($rarity > 1 && $random->nextBoundedInt($rarity) !== 0){
					continue;
				}
				$baseX = $rx * Chunk::EDGE_LENGTH;
				$baseZ = $rz * Chunk::EDGE_LENGTH;
				$lowY = max($boundsMinY + 2, $minAnchorY);
				$highY = min($boundsMaxY - 6, $maxAnchorY);
				if($highY <= $lowY){
					continue;
				}
				for($a = 0; $a < self::ANCHOR_ATTEMPTS; ++$a){
					$x = $baseX + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
					$z = $baseZ + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
					$startY = $lowY + $random->nextBoundedInt($highY - $lowY);
					if($attempt($x, $z, $startY, $lowY, $highY, $random)){
						break;
					}
				}
			}
		}
	}

	/**
	 * Scans up from startY for an air cell whose block below is solid (a cave floor).
	 */
	private function findCaveFloor(GenerationVolume $volume, int $x, int $startY, int $z, int $lowY, int $highY) : ?int{
		for($y = $startY; $y <= $highY; ++$y){
			if(
				$volume->isInBounds($x, $y, $z) &&
				$volume->getBlockStateId($x, $y, $z) === $this->airStateId &&
				$volume->getBlockStateId($x, $y - 1, $z) !== $this->airStateId
			){
				return $y;
			}
		}
		return null;
	}
}
