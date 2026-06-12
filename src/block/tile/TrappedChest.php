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

namespace pocketmine\block\tile;

use pocketmine\block\inventory\DoubleTrappedChestInventory;
use pocketmine\block\inventory\TrappedChestInventory;
use pocketmine\math\Vector3;
use pocketmine\world\World;

class TrappedChest extends Chest{

	public function __construct(World $world, Vector3 $pos){
		parent::__construct($world, $pos);
		$this->inventory = new TrappedChestInventory($this->position);
	}

	protected function checkPairing() : void{
		parent::checkPairing();
		if($this->doubleInventory !== null && !($this->doubleInventory instanceof DoubleTrappedChestInventory)){
			$pair = $this->getPair();
			if($pair instanceof self){
				if(($pair->position->x + ($pair->position->z << 15)) > ($this->position->x + ($this->position->z << 15))){
					$newInv = new DoubleTrappedChestInventory($pair->inventory, $this->inventory);
				}else{
					$newInv = new DoubleTrappedChestInventory($this->inventory, $pair->inventory);
				}
				$this->doubleInventory = $pair->doubleInventory = $newInv;
			}
		}
	}
}
