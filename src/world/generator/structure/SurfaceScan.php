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
use pocketmine\block\RuntimeBlockStateRegistry;

/**
 * Finds the top SOLID ground block of a column, used to anchor surface structures. A single implementation is shared by
 * the async {@link SurfaceStructurePopulator} (reading a {@link \pocketmine\world\generator\carver\GenerationVolume}) and
 * the main-thread {@link OverworldStructureFurnisher} (reading the live {@link \pocketmine\world\World}); both pass a
 * closure that yields the block state id at a given Y, so the two cannot drift in how they define "the surface".
 *
 * The scan walks DOWN from $topY and returns the first non-transparent solid block - i.e. it skips air, water, leaves,
 * the snow layer and other transparent cover (mirroring the GroundCover top-solid idiom). Surface structures anchor on a
 * column they never build over, so this scan returns the same ground Y no matter which chunk re-derives the structure.
 */
final class SurfaceScan{

	private function __construct(){
		//NOOP
	}

	/**
	 * @param Closure $stateIdAt fn(int $y) : int - the block state id at the scanned column at world height $y
	 * @phpstan-param Closure(int) : int $stateIdAt
	 *
	 * @return int|null the Y of the topmost solid ground block in [$minY, $topY], or null if the column has none
	 */
	public static function topSolidY(Closure $stateIdAt, int $topY, int $minY) : ?int{
		$registry = RuntimeBlockStateRegistry::getInstance();
		for($y = $topY; $y >= $minY; --$y){
			$block = $registry->fromStateId($stateIdAt($y));
			if($block->isSolid() && !$block->isTransparent()){
				return $y;
			}
		}
		return null;
	}
}
