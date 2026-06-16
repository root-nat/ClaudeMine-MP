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

namespace pocketmine\world\generator\carver;

/**
 * In-memory {@link GenerationVolume} for unit tests. Loaded chunks default to STONE; bedrock/air can be set explicitly.
 * Avoids the native PalettedBlockArray-backed Chunk so carver geometry can be tested in pure PHP.
 */
final class ArrayVolume implements GenerationVolume{
	public const AIR = 0;
	public const STONE = 1;
	public const BEDROCK = 2;

	/** @var array<string, int> */
	private array $blocks = [];
	/** @var array<string, bool> */
	private array $loadedChunks = [];

	public function __construct(
		private int $minY = -64,
		private int $maxY = 320
	){}

	public function loadChunk(int $chunkX, int $chunkZ) : void{
		$this->loadedChunks["$chunkX:$chunkZ"] = true;
	}

	public function loadRegion(int $minChunkX, int $maxChunkX, int $minChunkZ, int $maxChunkZ) : void{
		for($cx = $minChunkX; $cx <= $maxChunkX; ++$cx){
			for($cz = $minChunkZ; $cz <= $maxChunkZ; ++$cz){
				$this->loadChunk($cx, $cz);
			}
		}
	}

	private function chunkLoaded(int $x, int $z) : bool{
		return $this->loadedChunks[($x >> 4) . ":" . ($z >> 4)] ?? false;
	}

	public function getMinY() : int{
		return $this->minY;
	}

	public function getMaxY() : int{
		return $this->maxY;
	}

	public function isInBounds(int $x, int $y, int $z) : bool{
		return $y >= $this->minY && $y < $this->maxY && $this->chunkLoaded($x, $z);
	}

	public function getBlockStateId(int $x, int $y, int $z) : int{
		$key = "$x:$y:$z";
		if(isset($this->blocks[$key])){
			return $this->blocks[$key];
		}
		return $this->isInBounds($x, $y, $z) ? self::STONE : self::AIR;
	}

	public function setBlockStateId(int $x, int $y, int $z, int $stateId) : void{
		if($this->chunkLoaded($x, $z)){
			$this->blocks["$x:$y:$z"] = $stateId;
		}
	}

	/**
	 * Returns the sorted set of cells that are currently air, for snapshot/determinism comparison.
	 *
	 * @return string[]
	 */
	public function airCells() : array{
		$cells = [];
		foreach($this->blocks as $key => $id){
			if($id === self::AIR){
				$cells[] = $key;
			}
		}
		sort($cells);
		return $cells;
	}
}
