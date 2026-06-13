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

use pocketmine\block\tile\Comparator;
use pocketmine\block\tile\Container;
use pocketmine\block\utils\AnalogRedstoneSignalEmitter;
use pocketmine\block\utils\AnalogRedstoneSignalEmitterTrait;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use function assert;
use function floor;
use function max;
use function min;

class RedstoneComparator extends Flowable implements AnalogRedstoneSignalEmitter, PoweredByRedstone, HorizontalFacing{
	use HorizontalFacingTrait;
	use AnalogRedstoneSignalEmitterTrait;
	use PoweredByRedstoneTrait;
	use StaticSupportTrait;

	protected bool $isSubtractMode = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacing($this->facing);
		$w->bool($this->isSubtractMode);
		$w->bool($this->powered);
	}

	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();
		$tile = $this->position->getWorld()->getTile($this->position);
		if($tile instanceof Comparator){
			$this->signalStrength = $tile->getSignalStrength();
		}

		return $this;
	}

	public function writeStateToWorld() : void{
		parent::writeStateToWorld();
		$tile = $this->position->getWorld()->getTile($this->position);
		assert($tile instanceof Comparator);
		$tile->setSignalStrength($this->signalStrength);
	}

	public function isSubtractMode() : bool{
		return $this->isSubtractMode;
	}

	/** @return $this */
	public function setSubtractMode(bool $isSubtractMode) : self{
		$this->isSubtractMode = $isSubtractMode;
		return $this;
	}

	protected function recalculateCollisionBoxes() : array{
		return [AxisAlignedBB::one()->trim(Facing::UP, 7 / 8)];
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			$this->facing = Facing::opposite($player->getHorizontalFacing());
		}
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		$this->isSubtractMode = !$this->isSubtractMode;
		$this->position->getWorld()->setBlock($this->position, $this);
		return true;
	}

	private function canBeSupportedAt(Block $block) : bool{
		return $block->getAdjacentSupportType(Facing::DOWN) !== SupportType::NONE;
	}

	public function onPostPlace() : void{
		$this->updateState();
	}

	public function onNearbyBlockChange() : void{
		if(!$this->canBeSupportedAt($this)){
			$this->position->getWorld()->useBreakOn($this->position);
			return;
		}
		$this->updateState();
	}

	public function onScheduledUpdate() : void{
		$newSignal = $this->calculateOutputSignal();
		if($newSignal !== $this->signalStrength || ($newSignal > 0) !== $this->powered){
			$this->signalStrength = $newSignal;
			$this->powered = $newSignal > 0;
			$world = $this->position->getWorld();
			$world->setBlock($this->position, $this);
			$world->notifyNeighbourBlockUpdate($this->position->getSide(Facing::opposite($this->facing)));
		}
	}

	public function getWeakRedstonePower(int $face) : int{
		return $face === Facing::opposite($this->facing) ? $this->signalStrength : 0;
	}

	public function getStrongRedstonePower(int $face) : int{
		return $this->getWeakRedstonePower($face);
	}

	private function updateState() : void{
		if($this->calculateOutputSignal() !== $this->signalStrength){
			$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 2);
		}
	}

	private function calculateOutputSignal() : int{
		$rear = $this->calculateRearInput();
		$side = max(
			$this->calculateSideInput(Facing::rotateY($this->facing, true)),
			$this->calculateSideInput(Facing::rotateY($this->facing, false))
		);
		if($this->isSubtractMode){
			return max(0, $rear - $side);
		}
		return $rear >= $side ? $rear : 0;
	}

	private function calculateRearInput() : int{
		$tile = $this->position->getWorld()->getTile($this->position->getSide($this->facing));
		if($tile instanceof Container){
			return self::calculateContainerSignal($tile->getInventory());
		}
		$input = $this->getSide($this->facing);
		$inputFace = Facing::opposite($this->facing);
		return max($input->getWeakRedstonePower($inputFace), $input->getStrongRedstonePower($inputFace));
	}

	private function calculateSideInput(int $face) : int{
		$side = $this->getSide($face);
		if($side instanceof RedstoneWire){
			return $side->getOutputSignalStrength();
		}
		if($side instanceof RedstoneRepeater || $side instanceof RedstoneComparator){
			return $side->getWeakRedstonePower(Facing::opposite($face));
		}
		return 0;
	}

	public static function calculateContainerSignal(Inventory $inventory) : int{
		$size = $inventory->getSize();
		if($size === 0){
			return 0;
		}
		$fullness = 0.0;
		$hasItem = false;
		for($slot = 0; $slot < $size; ++$slot){
			$item = $inventory->getItem($slot);
			if($item->isNull()){
				continue;
			}
			$hasItem = true;
			$fullness += $item->getCount() / min($inventory->getMaxStackSize(), $item->getMaxStackSize());
		}
		if(!$hasItem){
			return 0;
		}
		return (int) floor(1 + ($fullness / $size) * 14);
	}
}
