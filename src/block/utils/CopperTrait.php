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

namespace pocketmine\block\utils;

use pocketmine\block\Block;
use pocketmine\color\Color;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Axe;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\particle\DustParticle;
use pocketmine\world\sound\CopperWaxApplySound;
use pocketmine\world\sound\CopperWaxRemoveSound;
use pocketmine\world\sound\ScrapeSound;
use function mt_rand;

trait CopperTrait{
	private CopperOxidation $oxidation = CopperOxidation::NONE;
	private bool $waxed = false;

	public function describeBlockItemState(RuntimeDataDescriber $w) : void{
		$w->enum($this->oxidation);
		$w->bool($this->waxed);
	}

	public function getOxidation() : CopperOxidation{ return $this->oxidation; }

	/** @return $this */
	public function setOxidation(CopperOxidation $oxidation) : self{
		$this->oxidation = $oxidation;
		return $this;
	}

	public function isWaxed() : bool{ return $this->waxed; }

	/** @return $this */
	public function setWaxed(bool $waxed) : self{
		$this->waxed = $waxed;
		return $this;
	}

	/**
	 * @param Item[] &$returnedItems
	 * @see Block::onInteract()
	 */
	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if(!$this->waxed && $item->getTypeId() === ItemTypeIds::HONEYCOMB){
			$this->waxed = true;
			$world = $this->position->getWorld();
			$world->setBlock($this->position, $this);
			for($i = 0; $i < 5; $i++){
				$world->addParticle($this->position->add(mt_rand(0, 100) / 100, mt_rand(0, 100) / 100, mt_rand(0, 100) / 100), new DustParticle(new Color(255, 166, 0)));
			}
			$world->addSound($this->position, new CopperWaxApplySound());
			$item->pop();
			return true;
		}

		if($item instanceof Axe){
			if($this->waxed){
				$this->waxed = false;
				$world = $this->position->getWorld();
				$world->setBlock($this->position, $this);
				for($i = 0; $i < 5; $i++){
					$world->addParticle($this->position->add(mt_rand(0, 100) / 100, mt_rand(0, 100) / 100, mt_rand(0, 100) / 100), new DustParticle(new Color(224, 224, 224)));
				}
				$world->addSound($this->position, new CopperWaxRemoveSound());
				$item->applyDamage(1);
				return true;
			}

			$previousOxidation = $this->oxidation->getPrevious();
			if($previousOxidation !== null){
				$this->oxidation = $previousOxidation;
				$world = $this->position->getWorld();
				$world->setBlock($this->position, $this);
				for($i = 0; $i < 5; $i++){
					$world->addParticle($this->position->add(mt_rand(0, 100) / 100, mt_rand(0, 100) / 100, mt_rand(0, 100) / 100), new DustParticle(new Color(45, 180, 130)));
				}
				$world->addSound($this->position, new ScrapeSound());
				$item->applyDamage(1);
				return true;
			}
		}

		return false;
	}
}
