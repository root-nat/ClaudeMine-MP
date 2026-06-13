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

use pocketmine\block\utils\Lightable;
use pocketmine\block\utils\LightableTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\math\Facing;

class RedstoneTorch extends Torch implements Lightable{
	use LightableTrait;

	private const TOGGLE_DELAY_TICKS = 2;

	public function __construct(BlockIdentifier $idInfo, string $name, BlockTypeInfo $typeInfo){
		$this->lit = true;
		parent::__construct($idInfo, $name, $typeInfo);
	}

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		parent::describeBlockOnlyState($w);
		$w->bool($this->lit);
	}

	public function getLightLevel() : int{
		return $this->lit ? 7 : 0;
	}

	public function onPostPlace() : void{
		$this->scheduleStateCheck();
	}

	public function onNearbyBlockChange() : void{
		parent::onNearbyBlockChange();
		if($this->position->getWorld()->getBlock($this->position)->hasSameTypeId($this)){
			$this->scheduleStateCheck();
		}
	}

	public function onScheduledUpdate() : void{
		$shouldBeLit = !$this->getSupportBlock()->isReceivingRedstonePower();
		if($shouldBeLit !== $this->lit){
			$this->lit = $shouldBeLit;
			$world = $this->position->getWorld();
			$world->setBlock($this->position, $this);
			$world->notifyNeighbourBlockUpdate($this->position->up());
		}
	}

	public function getWeakRedstonePower(int $face) : int{
		return $this->lit && $face !== Facing::opposite($this->facing) ? 15 : 0;
	}

	public function getStrongRedstonePower(int $face) : int{
		return $this->lit && $face === Facing::UP ? 15 : 0;
	}

	private function getSupportBlock() : Block{
		return $this->getSide(Facing::opposite($this->facing));
	}

	private function scheduleStateCheck() : void{
		if((!$this->getSupportBlock()->isReceivingRedstonePower()) !== $this->lit){
			$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, self::TOGGLE_DELAY_TICKS);
		}
	}
}
