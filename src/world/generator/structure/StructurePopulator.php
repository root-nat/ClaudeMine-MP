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

	public function __construct(
		private int $worldSeed,
		private DungeonStructure $dungeon,
		private int $rarity = 8,
		private int $minAnchorY = -54,
		private int $maxAnchorY = 46,
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
		//a dungeon anchored in any of the 8 neighbouring regions may reach into this chunk
		for($rx = $chunkX - 1; $rx <= $chunkX + 1; ++$rx){
			for($rz = $chunkZ - 1; $rz <= $chunkZ + 1; ++$rz){
				if($this->tryPlaceRegion($volume, $rx, $rz)){
					$placed = true;
				}
			}
		}
		return $placed;
	}

	private function tryPlaceRegion(GenerationVolume $volume, int $regionX, int $regionZ) : bool{
		$random = RegionRandom::derive($this->worldSeed, $regionX, $regionZ, 0x6a17);
		if($this->rarity > 1 && $random->nextBoundedInt($this->rarity) !== 0){
			return false;
		}

		$baseX = $regionX * Chunk::EDGE_LENGTH;
		$baseZ = $regionZ * Chunk::EDGE_LENGTH;
		$lowY = max($volume->getMinY() + 2, $this->minAnchorY);
		$highY = min($volume->getMaxY() - 6, $this->maxAnchorY);
		if($highY <= $lowY){
			return false;
		}

		for($attempt = 0; $attempt < self::ANCHOR_ATTEMPTS; ++$attempt){
			$x = $baseX + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
			$z = $baseZ + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
			$startY = $lowY + $random->nextBoundedInt($highY - $lowY);

			$anchorY = $this->findCaveFloor($volume, $x, $startY, $z, $lowY, $highY);
			if($anchorY !== null && $this->dungeon->canPlace($volume, $x, $anchorY, $z)){
				$this->dungeon->place($volume, $x, $anchorY, $z, $random);
				return true;
			}
		}
		return false;
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
