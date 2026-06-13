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

use pocketmine\utils\Random;

/**
 * Derives a deterministic {@link Random} for a (world seed, region x, region z) tuple. Carvers and structures whose
 * body spans several chunks seed themselves from the region a feature *originates* in, so a neighbouring chunk that
 * queries the same region reproduces the identical feature — giving cross-chunk continuity without any global state.
 * Matches the Normal generator's per-chunk seeding scheme.
 */
final class RegionRandom{

	private function __construct(){
	}

	public static function seedFor(int $worldSeed, int $regionX, int $regionZ, int $salt = 0) : int{
		return (0xdeadbeef ^ ($regionX << 8) ^ $regionZ ^ $worldSeed ^ ($salt * 0x9e3779b1)) & 0x7fffffffffffffff;
	}

	public static function derive(int $worldSeed, int $regionX, int $regionZ, int $salt = 0) : Random{
		return new Random(self::seedFor($worldSeed, $regionX, $regionZ, $salt));
	}
}
