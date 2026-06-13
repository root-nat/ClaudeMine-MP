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
use pocketmine\world\generator\carver\GenerationVolume;

/**
 * A placeable terrain feature built from block states. Like carvers, structures operate purely on a {@link
 * GenerationVolume}; they must never write outside its bounds (the populator picks an anchor; the structure clips to
 * loaded chunks).
 *
 * NOTE: only block STATES survive the async worker→main-thread boundary, so a structure may place e.g. a spawner or
 * chest block state but cannot configure its tile NBT (spawn mob / loot). Tile content is a separate main-thread step.
 */
abstract class Structure{

	/**
	 * Returns whether the structure can be placed with the given block as its anchor (typically the centre floor block).
	 */
	abstract public function canPlace(GenerationVolume $volume, int $x, int $y, int $z) : bool;

	/**
	 * Builds the structure geometry, anchored at (x, y, z).
	 */
	abstract public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void;
}
