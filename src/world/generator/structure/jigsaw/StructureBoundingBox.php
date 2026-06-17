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

namespace pocketmine\world\generator\structure\jigsaw;

/**
 * An inclusive integer block bounding box, used by the {@link JigsawAssembler} for piece-overlap and in-bounds checks.
 * Cell-based: two boxes that merely touch faces (one's max+1 equals the other's min) do NOT overlap.
 */
final class StructureBoundingBox{

	public function __construct(
		public readonly int $minX,
		public readonly int $minY,
		public readonly int $minZ,
		public readonly int $maxX,
		public readonly int $maxY,
		public readonly int $maxZ
	){}

	public function overlaps(StructureBoundingBox $other) : bool{
		return $this->minX <= $other->maxX && $other->minX <= $this->maxX
			&& $this->minY <= $other->maxY && $other->minY <= $this->maxY
			&& $this->minZ <= $other->maxZ && $other->minZ <= $this->maxZ;
	}

	public function containsBox(StructureBoundingBox $other) : bool{
		return $other->minX >= $this->minX && $other->maxX <= $this->maxX
			&& $other->minY >= $this->minY && $other->maxY <= $this->maxY
			&& $other->minZ >= $this->minZ && $other->maxZ <= $this->maxZ;
	}

	public function intersectsChunk(int $chunkX, int $chunkZ) : bool{
		$minCX = $chunkX << 4;
		$minCZ = $chunkZ << 4;
		return $this->minX <= $minCX + 15 && $this->maxX >= $minCX
			&& $this->minZ <= $minCZ + 15 && $this->maxZ >= $minCZ;
	}
}
