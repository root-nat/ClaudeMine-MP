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
use function intdiv;

/**
 * Places nether fortresses after terrain. Like {@link StructurePopulator} the decision/geometry is pure logic over a
 * {@link GenerationVolume} ({@link self::tryPlace}, unit-tested in memory) and {@link self::populate} is the live
 * ChunkManager bridge. Unlike the dungeon populator, a fortress is large and rare and is anchored at a fixed deck height
 * over the lava seas (no cave-floor search), so every chunk it spans scans far enough to re-derive it: the scan radius is
 * the fortress footprint expressed in chunks, guaranteeing a fortress originating up to that many chunks away is found.
 */
final class NetherFortressPopulator implements Populator{

	private const SALT = 0x4f0717;

	private int $scanRadius;

	public function __construct(
		private int $worldSeed,
		private NetherFortressStructure $fortress,
		private int $rarity = 128,
		private int $minDeckY = 50,
		private int $maxDeckY = 78,
		int $footprintRadius = NetherFortressStructure::MAX_RADIUS
	){
		//a fortress anchored anywhere in its origin chunk reaches footprintRadius blocks out, i.e. up to this many chunks
		$this->scanRadius = intdiv($footprintRadius + Chunk::EDGE_LENGTH - 1, Chunk::EDGE_LENGTH);
	}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$this->tryPlace(new ChunkManagerVolume($world), $chunkX, $chunkZ);
	}

	/**
	 * Attempts to place every fortress whose origin region (a single chunk) lies within the scan radius of the given
	 * chunk and therefore may reach into it. Each is re-derived identically from its region seed, so a fortress spanning
	 * a chunk boundary is reproduced rather than half-generated. Returns true if at least one fortress was placed.
	 * Deterministic for a given world seed.
	 */
	public function tryPlace(GenerationVolume $volume, int $chunkX, int $chunkZ) : bool{
		$placed = false;
		for($rx = $chunkX - $this->scanRadius; $rx <= $chunkX + $this->scanRadius; ++$rx){
			for($rz = $chunkZ - $this->scanRadius; $rz <= $chunkZ + $this->scanRadius; ++$rz){
				if($this->tryPlaceRegion($volume, $rx, $rz)){
					$placed = true;
				}
			}
		}
		return $placed;
	}

	private function tryPlaceRegion(GenerationVolume $volume, int $regionX, int $regionZ) : bool{
		$random = RegionRandom::derive($this->worldSeed, $regionX, $regionZ, self::SALT);
		if($this->rarity > 1 && $random->nextBoundedInt($this->rarity) !== 0){
			return false;
		}
		if($this->maxDeckY <= $this->minDeckY){
			return false;
		}

		//draw the anchor before placing; the sequence is identical in every chunk that re-derives this region, and the
		//structure reads nothing from the volume, so the geometry never diverges across chunk boundaries
		$x = $regionX * Chunk::EDGE_LENGTH + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
		$z = $regionZ * Chunk::EDGE_LENGTH + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
		$y = $this->minDeckY + $random->nextBoundedInt($this->maxDeckY - $this->minDeckY + 1);

		if(!$this->fortress->canPlace($volume, $x, $y, $z)){
			return false;
		}
		$this->fortress->place($volume, $x, $y, $z, $random);
		return true;
	}
}
