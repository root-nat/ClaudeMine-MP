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

use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\AnyFacingTrait;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\player\Player;

/**
 * The "head" block placed in front of an extended piston. Removing it removes the piston base, and vice versa.
 */
class PistonArmCollision extends Transparent implements AnyFacing{
	use AnyFacingTrait;

	public function isSolid() : bool{
		return false;
	}

	public function getDrops(Item $item) : array{
		return [];
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		if(parent::onBreak($item, $player, $returnedItems)){
			$base = $this->getSide(Facing::opposite($this->facing));
			if($base instanceof Piston){
				$this->position->getWorld()->setBlock($base->getPosition(), VanillaBlocks::AIR());
			}
			return true;
		}
		return false;
	}
}
