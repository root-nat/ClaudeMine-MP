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

use pocketmine\block\tile\Observer as TileObserver;
use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\AnyFacingTrait;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

class Observer extends Opaque implements AnyFacing, PoweredByRedstone{
	use AnyFacingTrait;
	use PoweredByRedstoneTrait;

	private const PULSE_TICKS = 4;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
		$w->bool($this->powered);
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			$this->facing = Facing::opposite($player->getHorizontalFacing());
			$pitch = $player->getLocation()->getPitch();
			if($pitch > 48){
				$this->facing = Facing::UP;
			}elseif($pitch < -48){
				$this->facing = Facing::DOWN;
			}
		}
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onPostPlace() : void{
		$tile = $this->position->getWorld()->getTile($this->position);
		if($tile instanceof TileObserver){
			$tile->setLastObservedStateId($this->getSide($this->facing)->getStateId());
		}
	}

	public function onNearbyBlockChange() : void{
		if($this->powered){
			return;
		}
		$world = $this->position->getWorld();
		$tile = $world->getTile($this->position);
		if(!$tile instanceof TileObserver){
			return;
		}
		$observedStateId = $this->getSide($this->facing)->getStateId();
		if($tile->getLastObservedStateId() !== $observedStateId){
			$tile->setLastObservedStateId($observedStateId);
			$this->powered = true;
			$world->setBlock($this->position, $this);
			$world->scheduleDelayedBlockUpdate($this->position, self::PULSE_TICKS);
			$world->notifyNeighbourBlockUpdate($this->position->getSide(Facing::opposite($this->facing)));
		}
	}

	public function onScheduledUpdate() : void{
		if($this->powered){
			$this->powered = false;
			$world = $this->position->getWorld();
			$tile = $world->getTile($this->position);
			if($tile instanceof TileObserver){
				$tile->setLastObservedStateId($this->getSide($this->facing)->getStateId());
			}
			$world->setBlock($this->position, $this);
			$world->notifyNeighbourBlockUpdate($this->position->getSide(Facing::opposite($this->facing)));
		}
	}

	public function getWeakRedstonePower(int $face) : int{
		return $this->getStrongRedstonePower($face);
	}

	public function getStrongRedstonePower(int $face) : int{
		return $this->powered && $face === Facing::opposite($this->facing) ? 15 : 0;
	}

	public function isRedstoneConductor() : bool{
		return false;
	}
}
