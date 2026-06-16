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

use pocketmine\entity\Strider;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

/**
 * The control item for a saddled {@link Strider}: hold it to steer the strider toward where you look, and right-click to
 * urge it into a short burst of speed, wearing the stick down a little each time.
 */
class WarpedFungusOnAStick extends Durable{

	public function getMaxStackSize() : int{
		return 1;
	}

	public function getMaxDurability() : int{
		return 100;
	}

	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		$strider = $this->findRiddenStrider($player);
		if($strider === null){
			return ItemUseResult::NONE;
		}
		$strider->boost();
		$this->applyDamage(1);
		return ItemUseResult::SUCCESS;
	}

	private function findRiddenStrider(Player $player) : ?Strider{
		foreach($player->getWorld()->getNearbyEntities($player->getBoundingBox()->expandedCopy(2.0, 2.0, 2.0)) as $entity){
			if($entity instanceof Strider && $entity->getRider() === $player){
				return $entity;
			}
		}
		return null;
	}
}
