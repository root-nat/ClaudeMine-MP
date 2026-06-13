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

namespace pocketmine\item;

use pocketmine\block\BaseRail;
use pocketmine\block\Block;
use pocketmine\entity\Location;
use pocketmine\entity\object\Minecart as MinecartEntity;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class Minecart extends Item{

	public function getMaxStackSize() : int{
		return 1;
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		if(!$blockClicked instanceof BaseRail){
			return ItemUseResult::NONE;
		}

		$world = $player->getWorld();
		$spawnPos = $blockClicked->getPosition()->add(0.5, 0.0625, 0.5);
		$entity = new MinecartEntity(Location::fromObject($spawnPos, $world, $player->getLocation()->getYaw(), 0));
		if($this->hasCustomName()){
			$entity->setNameTag($this->getCustomName());
		}
		$entity->spawnToAll();
		$this->pop();

		return ItemUseResult::SUCCESS;
	}
}
