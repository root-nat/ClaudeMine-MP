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

namespace pocketmine\block;

use pocketmine\item\Item;

/**
 * Transient technical block (Bedrock minecraft:moving_block) placed where a real block is currently being slid by a
 * piston. It carries a {@link \pocketmine\block\tile\MovingBlock} tile holding the block being moved and the controlling
 * piston; the client renders that block sliding. The piston replaces it with the real block once the animation finishes.
 */
class MovingBlock extends Transparent{

	public function isSolid() : bool{
		return false;
	}

	protected function recalculateCollisionBoxes() : array{
		return [];
	}

	public function canBeReplaced() : bool{
		return true;
	}

	public function getDrops(Item $item) : array{
		return [];
	}

	public function isFireProofAsItem() : bool{
		return true;
	}
}
