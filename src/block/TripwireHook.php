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

use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\block\utils\TripwireHookLogic;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\sound\RedstonePowerOffSound;
use pocketmine\world\sound\RedstonePowerOnSound;

class TripwireHook extends Flowable implements HorizontalFacing{
	use HorizontalFacingTrait;

	protected bool $connected = false;
	protected bool $powered = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacing($this->facing);
		$w->bool($this->connected);
		$w->bool($this->powered);
	}

	public function isConnected() : bool{ return $this->connected; }

	/** @return $this */
	public function setConnected(bool $connected) : self{
		$this->connected = $connected;
		return $this;
	}

	public function isPowered() : bool{ return $this->powered; }

	/** @return $this */
	public function setPowered(bool $powered) : self{
		$this->powered = $powered;
		return $this;
	}

	private function canBeSupportedAt(Block $block, int $face) : bool{
		return $block->getAdjacentSupportType($face)->hasCenterSupport();
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if(Facing::axis($face) === Axis::Y || !$this->canBeSupportedAt($blockReplace, Facing::opposite($face))){
			return false;
		}
		$this->facing = $face;
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onPostPlace() : void{
		$this->recalculateState();
	}

	public function onNearbyBlockChange() : void{
		if(!$this->canBeSupportedAt($this, Facing::opposite($this->facing))){
			$this->position->getWorld()->useBreakOn($this->position);
			return;
		}
		$this->recalculateState();
	}

	/**
	 * Scans the line in front of the hook for a partner hook and, if found, marks the whole circuit connected and powers
	 * it when any string in between has an entity on it. Drives both end hooks and the strings between them.
	 */
	public function recalculateState() : void{
		$world = $this->position->getWorld();
		$cells = [];
		/** @var array<int, array{Vector3, Tripwire}> $wires */
		$wires = [];
		$far = null;
		for($d = 1; $d <= TripwireHookLogic::MAX_DISTANCE; ++$d){
			$pos = $this->position->getSide($this->facing, $d);
			$block = $world->getBlock($pos);
			if($block instanceof TripwireHook){
				$cells[] = $block->getFacing() === Facing::opposite($this->facing) ? TripwireHookLogic::CELL_MATCHING_HOOK : TripwireHookLogic::CELL_BLOCKED;
				if($block->getFacing() === Facing::opposite($this->facing)){
					$far = $block;
				}
				break;
			}
			if($block instanceof Tripwire){
				$cells[] = $block->isTriggered() ? TripwireHookLogic::CELL_WIRE_TRIGGERED : TripwireHookLogic::CELL_WIRE;
				$wires[$d] = [$pos, $block];
				continue;
			}
			$cells[] = TripwireHookLogic::CELL_BLOCKED;
			break;
		}

		[$connected, $distance, $powered] = TripwireHookLogic::scan($cells);

		$this->applyState($connected, $powered);
		if($connected && $far !== null){
			$far->applyState($connected, $powered);
		}

		foreach($wires as $d => [$pos, $wire]){
			$shouldConnect = $connected && $d < $distance;
			if($wire->isConnected() !== $shouldConnect){
				$wire->setConnected($shouldConnect);
				$world->setBlock($pos, $wire);
			}
		}
	}

	/**
	 * Applies the computed circuit state to this hook and, on a power change, plays the click and pokes redstone.
	 */
	public function applyState(bool $connected, bool $powered) : void{
		if($this->connected === $connected && $this->powered === $powered){
			return;
		}
		$poweredChanged = $this->powered !== $powered;
		$this->connected = $connected;
		$this->powered = $powered;

		$world = $this->position->getWorld();
		$world->setBlock($this->position, $this);
		if($poweredChanged){
			$world->addSound($this->position->add(0.5, 0.5, 0.5), $powered ? new RedstonePowerOnSound() : new RedstonePowerOffSound());
			$world->notifyNeighbourBlockUpdate($this->position);
			$world->notifyNeighbourBlockUpdate($this->position->getSide(Facing::opposite($this->facing)));
		}
	}

	public function getWeakRedstonePower(int $face) : int{
		return $this->powered ? 15 : 0;
	}

	public function getStrongRedstonePower(int $face) : int{
		return $this->powered && $face === Facing::opposite($this->facing) ? 15 : 0;
	}
}
