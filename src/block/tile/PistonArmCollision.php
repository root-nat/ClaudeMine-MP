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

use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use function count;

/**
 * Block-entity on a piston BASE block (Bedrock id "PistonArmCollision") that drives the extend/retract animation. The
 * client animates the arm and any attached {@link \pocketmine\block\MovingBlock} blocks from this State/Progress.
 */
class PistonArmCollision extends Spawnable{
	public const STATE_RETRACTED = 0;
	public const STATE_EXTENDING = 1;
	public const STATE_EXTENDED = 2;
	public const STATE_RETRACTING = 3;

	private const TAG_STATE = "State";
	private const TAG_NEW_STATE = "NewState";
	private const TAG_PROGRESS = "Progress";
	private const TAG_LAST_PROGRESS = "LastProgress";
	private const TAG_STICKY = "Sticky";
	private const TAG_ATTACHED_BLOCKS = "AttachedBlocks";
	private const TAG_BREAK_BLOCKS = "BreakBlocks";

	private int $state = self::STATE_RETRACTED;
	private int $newState = self::STATE_RETRACTED;
	private float $progress = 0.0;
	private float $lastProgress = 0.0;
	private bool $sticky = false;

	/** @var Vector3[] */
	private array $attachedBlocks = [];
	/** @var Vector3[] */
	private array $breakBlocks = [];

	public function getState() : int{ return $this->state; }

	public function getNewState() : int{ return $this->newState; }

	public function isSticky() : bool{ return $this->sticky; }

	public function setSticky(bool $sticky) : void{ $this->sticky = $sticky; }

	public function isMoving() : bool{
		return $this->state === self::STATE_EXTENDING || $this->state === self::STATE_RETRACTING;
	}

	/**
	 * @return Vector3[]
	 */
	public function getAttachedBlocks() : array{
		return $this->attachedBlocks;
	}

	/**
	 * @param Vector3[] $attachedBlocks
	 * @param Vector3[] $breakBlocks
	 */
	public function startMovement(int $state, float $progress, array $attachedBlocks = [], array $breakBlocks = []) : void{
		$this->state = $state;
		$this->newState = $state === self::STATE_EXTENDING ? self::STATE_EXTENDED : self::STATE_RETRACTED;
		$this->lastProgress = $this->progress;
		$this->progress = $progress;
		$this->attachedBlocks = $attachedBlocks;
		$this->breakBlocks = $breakBlocks;
	}

	public function finishMovement(int $state) : void{
		$this->state = $state;
		$this->newState = $state;
		$this->lastProgress = $this->progress;
		$this->progress = $state === self::STATE_EXTENDED ? 1.0 : 0.0;
		$this->attachedBlocks = [];
		$this->breakBlocks = [];
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->state = $nbt->getByte(self::TAG_STATE, self::STATE_RETRACTED);
		$this->newState = $nbt->getByte(self::TAG_NEW_STATE, $this->state);
		$this->progress = $nbt->getFloat(self::TAG_PROGRESS, 0.0);
		$this->lastProgress = $nbt->getFloat(self::TAG_LAST_PROGRESS, 0.0);
		$this->sticky = $nbt->getByte(self::TAG_STICKY, 0) !== 0;
		$this->attachedBlocks = self::readPositionList($nbt, self::TAG_ATTACHED_BLOCKS);
		$this->breakBlocks = self::readPositionList($nbt, self::TAG_BREAK_BLOCKS);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$this->writeState($nbt);
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$this->writeState($nbt);
	}

	private function writeState(CompoundTag $nbt) : void{
		$nbt->setByte(self::TAG_STATE, $this->state);
		$nbt->setByte(self::TAG_NEW_STATE, $this->newState);
		$nbt->setFloat(self::TAG_PROGRESS, $this->progress);
		$nbt->setFloat(self::TAG_LAST_PROGRESS, $this->lastProgress);
		$nbt->setByte(self::TAG_STICKY, $this->sticky ? 1 : 0);
		$nbt->setTag(self::TAG_ATTACHED_BLOCKS, self::writePositionList($this->attachedBlocks));
		$nbt->setTag(self::TAG_BREAK_BLOCKS, self::writePositionList($this->breakBlocks));
	}

	/**
	 * @param Vector3[] $positions
	 */
	private static function writePositionList(array $positions) : ListTag{
		$list = new ListTag([], NBT::TAG_Int);
		foreach($positions as $pos){
			$list->push(new IntTag($pos->getFloorX()));
			$list->push(new IntTag($pos->getFloorY()));
			$list->push(new IntTag($pos->getFloorZ()));
		}
		return $list;
	}

	/**
	 * @return Vector3[]
	 */
	private static function readPositionList(CompoundTag $nbt, string $key) : array{
		$positions = [];
		$list = $nbt->getListTag($key);
		if($list !== null && $list->getTagType() === NBT::TAG_Int){
			$values = [];
			/** @var IntTag $tag */
			foreach($list as $tag){
				$values[] = $tag->getValue();
			}
			$count = count($values);
			for($i = 0; $i + 2 < $count; $i += 3){
				$positions[] = new Vector3($values[$i], $values[$i + 1], $values[$i + 2]);
			}
		}
		return $positions;
	}
}
