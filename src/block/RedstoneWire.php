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

use pocketmine\block\utils\AnalogRedstoneSignalEmitter;
use pocketmine\block\utils\AnalogRedstoneSignalEmitterTrait;
use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use function max;
use function min;

class RedstoneWire extends Flowable implements AnalogRedstoneSignalEmitter{
	use AnalogRedstoneSignalEmitterTrait;
	use StaticSupportTrait;

	public function onPostPlace() : void{
		$this->recalculateSignal();
	}

	public function onNearbyBlockChange() : void{
		if(!$this->canBeSupportedAt($this)){
			$this->position->getWorld()->useBreakOn($this->position);
			return;
		}
		$this->recalculateSignal();
	}

	private function recalculateSignal() : void{
		$newStrength = $this->calculateSignalStrength();
		if($newStrength !== $this->signalStrength){
			$this->signalStrength = $newStrength;
			$world = $this->position->getWorld();
			$world->setBlock($this->position, $this);
			foreach(Facing::HORIZONTAL as $face){
				$side = $this->position->getSide($face);
				$world->notifyNeighbourBlockUpdate($side->up());
				$world->notifyNeighbourBlockUpdate($side->down());
			}
		}
	}

	private function calculateSignalStrength() : int{
		$power = 0;

		foreach(Facing::ALL as $face){
			$side = $this->getSide($face);
			if(!($side instanceof self)){
				$opposite = Facing::opposite($face);
				$power = max($power, $side->getWeakRedstonePower($opposite), $side->getStrongRedstonePower($opposite));
				if($power >= 15){
					return 15;
				}
			}
		}

		$aboveIsConductor = $this->getSide(Facing::UP)->isRedstoneConductor();
		foreach(Facing::HORIZONTAL as $face){
			$side = $this->getSide($face);
			if($side instanceof self){
				$power = max($power, $side->getOutputSignalStrength() - 1);
			}elseif($side->isRedstoneConductor()){
				if(!$aboveIsConductor){
					$above = $side->getSide(Facing::UP);
					if($above instanceof self){
						$power = max($power, $above->getOutputSignalStrength() - 1);
					}
				}
			}else{
				$below = $side->getSide(Facing::DOWN);
				if($below instanceof self){
					$power = max($power, $below->getOutputSignalStrength() - 1);
				}
			}
		}

		return min(15, max(0, $power));
	}

	public function getWeakRedstonePower(int $face) : int{
		return $face === Facing::UP || $this->signalStrength === 0 ? 0 : $this->signalStrength;
	}

	private function canBeSupportedAt(Block $block) : bool{
		return $block->getAdjacentSupportType(Facing::DOWN)->hasCenterSupport();
	}

	public function asItem() : Item{
		return VanillaItems::REDSTONE_DUST();
	}
}
