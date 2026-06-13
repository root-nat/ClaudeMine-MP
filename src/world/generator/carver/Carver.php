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
 * Carves negative-space features (caves, ravines) into a chunk. Carvers must be deterministic and self-contained so
 * they produce identical output regardless of the order chunks are generated across worker threads.
 */
interface Carver{

	/**
	 * Carves features overlapping the given chunk. Implementations must only write inside the volume's bounds (features
	 * reaching unloaded neighbours simply stop; the neighbour re-derives the same feature when it generates).
	 */
	public function carve(GenerationVolume $volume, int $chunkX, int $chunkZ, int $worldSeed) : void;
}
