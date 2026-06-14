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

namespace pocketmine\entity;

use pocketmine\item\Bowl;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;

/**
 * Mushroom cow: behaves like a cow (same breeding, drops, baby) but you can milk mushroom stew from it with an empty bowl.
 */
class Mooshroom extends Cow{

	public static function getNetworkTypeId() : string{ return EntityIds::MOOSHROOM; }

	public function getName() : string{
		return "Mooshroom";
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		if($item instanceof Bowl && !$this->isBaby()){
			if($player->hasFiniteResources()){
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				$player->getInventory()->addItem(VanillaItems::MUSHROOM_STEW());
			}
			return true;
		}
		return parent::onInteract($player, $clickPos);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::MOOSHROOM_SPAWN_EGG();
	}
}
