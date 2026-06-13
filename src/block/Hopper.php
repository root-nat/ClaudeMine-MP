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

use pocketmine\block\tile\Container;
use pocketmine\block\tile\Hopper as TileHopper;
use pocketmine\block\utils\HopperTransferHelper;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\object\ItemEntity;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\World;

class Hopper extends Transparent implements PoweredByRedstone{
	use PoweredByRedstoneTrait;

	private int $facing = Facing::DOWN;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facingExcept($this->facing, Facing::UP);
		$w->bool($this->powered);
	}

	public function getFacing() : int{ return $this->facing; }

	/** @return $this */
	public function setFacing(int $facing) : self{
		if($facing === Facing::UP){
			throw new \InvalidArgumentException("Hopper may not face upward");
		}
		$this->facing = $facing;
		return $this;
	}

	protected function recalculateCollisionBoxes() : array{
		$result = [
			AxisAlignedBB::one()->trim(Facing::UP, 6 / 16) //the empty area around the bottom is currently considered solid
		];

		foreach(Facing::HORIZONTAL as $f){ //add the frame parts around the bowl
			$result[] = AxisAlignedBB::one()->trim($f, 14 / 16);
		}
		return $result;
	}

	public function getSupportType(int $facing) : SupportType{
		return match($facing){
			Facing::UP => SupportType::FULL,
			Facing::DOWN => $this->facing === Facing::DOWN ? SupportType::CENTER : SupportType::NONE,
			default => SupportType::NONE
		};
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->facing = $face === Facing::DOWN ? Facing::DOWN : Facing::opposite($face);

		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($player !== null){
			$tile = $this->position->getWorld()->getTile($this->position);
			if($tile instanceof TileHopper){ //TODO: find a way to have inventories open on click without this boilerplate in every block
				$player->setCurrentWindow($tile->getInventory());
			}
			return true;
		}
		return false;
	}

	public function onPostPlace() : void{
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, TileHopper::TRANSFER_COOLDOWN_TICKS);
	}

	public function onNearbyBlockChange() : void{
		$world = $this->position->getWorld();
		$powered = $this->isReceivingRedstonePower();
		if($powered !== $this->powered){
			$this->powered = $powered;
			$world->setBlock($this->position, $this);
		}
		$world->scheduleDelayedBlockUpdate($this->position, 1);
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$tile = $world->getTile($this->position);
		if(!$tile instanceof TileHopper){
			return;
		}

		if(!$this->powered){
			$this->pushItems($world, $tile);
			if(!$this->pullItems($world, $tile)){
				$this->pickupItems($world, $tile);
			}
		}

		$world->scheduleDelayedBlockUpdate($this->position, TileHopper::TRANSFER_COOLDOWN_TICKS);
	}

	private function pushItems(World $world, TileHopper $tile) : bool{
		$destination = $world->getTile($this->position->getSide($this->facing));
		if(!$destination instanceof Container){
			return false;
		}
		return HopperTransferHelper::transferOneItem($tile->getInventory(), $destination->getInventory());
	}

	private function pullItems(World $world, TileHopper $tile) : bool{
		$source = $world->getTile($this->position->up());
		if(!$source instanceof Container){
			return false;
		}
		return HopperTransferHelper::transferOneItem($source->getInventory(), $tile->getInventory());
	}

	private function pickupItems(World $world, TileHopper $tile) : bool{
		$pickupArea = AxisAlignedBB::one()->offset($this->position->x, $this->position->y, $this->position->z)->extend(Facing::UP, 1);
		$inventory = $tile->getInventory();
		foreach($world->getNearbyEntities($pickupArea) as $entity){
			if(!$entity instanceof ItemEntity || $entity->isFlaggedForDespawn()){
				continue;
			}
			$item = $entity->getItem();
			if(!$inventory->canAddItem($item)){
				continue;
			}
			$inventory->addItem($item);
			$entity->flagForDespawn();
			return true;
		}
		return false;
	}
}
