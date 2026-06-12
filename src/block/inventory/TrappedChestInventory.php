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

namespace pocketmine\block\inventory;

use pocketmine\player\Player;
use pocketmine\world\Position;

class TrappedChestInventory extends ChestInventory{

	public function __construct(Position $holder){
		parent::__construct($holder);
	}

	public function onOpen(Player $who) : void{
		parent::onOpen($who);
		$this->notifyBlock();
	}

	public function onClose(Player $who) : void{
		parent::onClose($who);
		$this->notifyBlock();
	}

	private function notifyBlock() : void{
		$holder = $this->getHolder();
		if($holder->isValid()){
			$world = $holder->getWorld();
			$world->setBlock($holder, $world->getBlock($holder));
		}
	}
}
