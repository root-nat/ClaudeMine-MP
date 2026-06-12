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

	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();
		$this->signalStrength = $this->calculateSignalStrength();
		return $this;
	}

	public function onNearbyBlockChange() : void{
		$newStrength = $this->calculateSignalStrength();
		if($newStrength !== $this->signalStrength){
			$this->signalStrength = $newStrength;
			$this->position->getWorld()->setBlock($this->position, $this);
		}
	}

	public function getWeakRedstonePower(int $face) : int{
		return $face !== Facing::UP ? $this->signalStrength : 0;
	}

	private function calculateSignalStrength() : int{
		$maxSignal = 0;
		foreach(Facing::HORIZONTAL as $face){
			$neighbor = $this->getSide($face);
			if($neighbor instanceof self){
				$maxSignal = max($maxSignal, $neighbor->getOutputSignalStrength() - 1);
			}else{
				$maxSignal = max($maxSignal, $neighbor->getWeakRedstonePower(Facing::opposite($face)));
				$maxSignal = max($maxSignal, $neighbor->getStrongRedstonePower(Facing::opposite($face)));
			}
		}
		return min(15, max(0, $maxSignal));
	}

	private function canBeSupportedAt(Block $block) : bool{
		return $block->getAdjacentSupportType(Facing::DOWN)->hasCenterSupport();
	}

	public function asItem() : Item{
		return VanillaItems::REDSTONE_DUST();
	}
}
