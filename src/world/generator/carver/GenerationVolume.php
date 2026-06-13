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
 * Block-state-level view of the terrain a carver or structure operates on. This is the single seam between the pure
 * carving/structure logic and the live (native, thread-bound) Chunk classes, allowing the geometry to be unit-tested
 * against a plain in-memory array instead of a PalettedBlockArray-backed Chunk. Coordinates are absolute world
 * coordinates; out-of-bounds writes must be silently ignored by the carver (it queries isInBounds first).
 */
interface GenerationVolume{

	public function getMinY() : int;

	public function getMaxY() : int;

	public function isInBounds(int $x, int $y, int $z) : bool;

	/**
	 * Returns the block state ID at the given coordinates, or the air state ID if out of bounds/unloaded.
	 */
	public function getBlockStateId(int $x, int $y, int $z) : int;

	public function setBlockStateId(int $x, int $y, int $z, int $stateId) : void;
}
