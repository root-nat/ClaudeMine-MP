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

use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

/**
 * Production {@link GenerationVolume} backed by a live {@link ChunkManager} (the SimpleChunkManager the generator runs
 * against in its worker). Translates state-ID reads/writes to the ChunkManager's Block API.
 */
final class ChunkManagerVolume implements GenerationVolume{

	public function __construct(
		private ChunkManager $world
	){}

	public function getMinY() : int{
		return $this->world->getMinY();
	}

	public function getMaxY() : int{
		return $this->world->getMaxY();
	}

	public function isInBounds(int $x, int $y, int $z) : bool{
		return $this->world->isInWorld($x, $y, $z)
			&& $this->world->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE) !== null;
	}

	public function getBlockStateId(int $x, int $y, int $z) : int{
		return $this->world->getBlockAt($x, $y, $z)->getStateId();
	}

	public function setBlockStateId(int $x, int $y, int $z, int $stateId) : void{
		if($this->isInBounds($x, $y, $z)){
			$this->world->setBlockAt($x, $y, $z, RuntimeBlockStateRegistry::getInstance()->fromStateId($stateId));
		}
	}
}
