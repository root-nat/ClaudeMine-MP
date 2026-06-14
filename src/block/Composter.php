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

use pocketmine\block\tile\Hopper as TileHopper;
use pocketmine\block\utils\ComposterLogic;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Utils;

class Composter extends Transparent implements HopperInteractable{

	public const MAX_FILL_LEVEL = ComposterLogic::READY_LEVEL;

	protected int $fillLevel = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(0, self::MAX_FILL_LEVEL, $this->fillLevel);
	}

	public function getFillLevel() : int{ return $this->fillLevel; }

	/** @return $this */
	public function setFillLevel(int $fillLevel) : self{
		if($fillLevel < 0 || $fillLevel > self::MAX_FILL_LEVEL){
			throw new \InvalidArgumentException("Fill level must be in range 0 ... " . self::MAX_FILL_LEVEL);
		}
		$this->fillLevel = $fillLevel;
		return $this;
	}

	public function isReady() : bool{
		return ComposterLogic::isReady($this->fillLevel);
	}

	protected function recalculateCollisionBoxes() : array{
		$result = [AxisAlignedBB::one()->trim(Facing::UP, 14 / 16)]; //thin floor at the bottom of the bowl
		foreach(Facing::HORIZONTAL as $f){
			$result[] = AxisAlignedBB::one()->trim($f, 14 / 16); //full-height walls around the bowl
		}
		return $result;
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		$world = $this->position->getWorld();

		if(ComposterLogic::isReady($this->fillLevel)){
			$world->dropItem($this->position->add(0.5, 0.9, 0.5), VanillaItems::BONE_MEAL());
			$this->fillLevel = ComposterLogic::EMPTY_LEVEL;
			$world->setBlock($this->position, $this);
			return true;
		}

		$chance = ComposterLogic::getCompostChance($item);
		if($chance <= 0.0){
			return false;
		}

		//a compost attempt always consumes one item; the chance only decides whether the level rises (vanilla behaviour)
		$item->pop();
		if(ComposterLogic::rollSucceeds($chance, Utils::getRandomFloat())){
			$this->fillLevel = ComposterLogic::levelAfterCompost($this->fillLevel);
			$world->setBlock($this->position, $this);
		}
		return true;
	}

	/**
	 * A hopper pointing into the composter feeds it one compostable item (same rules as a manual interaction).
	 */
	public function doHopperPush(Hopper $hopperBlock) : bool{
		if(ComposterLogic::isReady($this->fillLevel)){
			return false;
		}
		$tileHopper = $this->position->getWorld()->getTile($hopperBlock->position);
		if(!$tileHopper instanceof TileHopper){
			return false;
		}

		$inventory = $tileHopper->getInventory();
		foreach($inventory->getContents() as $item){
			$chance = ComposterLogic::getCompostChance($item);
			if($chance <= 0.0){
				continue;
			}

			//vanilla parity: a composter ALWAYS consumes the inserted item (hopper-fed too, just like a manual
			//interaction) and the level only rises on a successful probability roll - this is why composter farms need
			//far more input items than the 8 levels. Returning true means the item was transferred, so the feeding hopper
			//correctly enters its transfer cooldown regardless of whether the level rose.
			$inventory->removeItem($item->pop());
			if(ComposterLogic::rollSucceeds($chance, Utils::getRandomFloat())){
				$this->fillLevel = ComposterLogic::levelAfterCompost($this->fillLevel);
				$this->position->getWorld()->setBlock($this->position, $this);
			}
			return true;
		}
		return false;
	}

	/**
	 * A hopper beneath the composter pulls one bone meal once it is ready, resetting it to empty.
	 */
	public function doHopperPull(Hopper $hopperBlock) : bool{
		if(!ComposterLogic::isReady($this->fillLevel)){
			return false;
		}
		$tileHopper = $this->position->getWorld()->getTile($hopperBlock->position);
		if(!$tileHopper instanceof TileHopper){
			return false;
		}

		$boneMeal = VanillaItems::BONE_MEAL();
		if(!$tileHopper->getInventory()->canAddItem($boneMeal)){
			return false;
		}

		$tileHopper->getInventory()->addItem($boneMeal);
		$this->fillLevel = ComposterLogic::EMPTY_LEVEL;
		$this->position->getWorld()->setBlock($this->position, $this);
		return true;
	}
}
