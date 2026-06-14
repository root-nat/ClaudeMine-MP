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

use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\data\bedrock\block\BlockStateDeserializeException;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\world\format\io\GlobalBlockStateHandlers;

/**
 * Block-entity carried by a {@link \pocketmine\block\MovingBlock}: it holds the real block being slid by a piston plus
 * the controlling piston's position, so the Bedrock client renders that block sliding toward/away from the piston.
 */
class MovingBlock extends Spawnable{
	private const TAG_MOVING_BLOCK = "movingBlock";
	private const TAG_PISTON_X = "pistonPosX";
	private const TAG_PISTON_Y = "pistonPosY";
	private const TAG_PISTON_Z = "pistonPosZ";

	private ?Block $movingBlock = null;
	private Vector3 $pistonPosition;

	public function getMovingBlock() : ?Block{
		return $this->movingBlock !== null ? clone $this->movingBlock : null;
	}

	public function setMovingBlock(Block $block) : void{
		$this->movingBlock = clone $block;
	}

	public function getPistonPosition() : ?Vector3{
		return isset($this->pistonPosition) ? $this->pistonPosition : null;
	}

	public function setPistonPosition(Vector3 $position) : void{
		$this->pistonPosition = $position->floor();
	}

	public function readSaveData(CompoundTag $nbt) : void{
		if(($movingBlockTag = $nbt->getCompoundTag(self::TAG_MOVING_BLOCK)) !== null){
			try{
				$blockStateData = GlobalBlockStateHandlers::getUpgrader()->upgradeBlockStateNbt($movingBlockTag);
				$blockStateId = GlobalBlockStateHandlers::getDeserializer()->deserialize($blockStateData);
				$this->movingBlock = RuntimeBlockStateRegistry::getInstance()->fromStateId($blockStateId);
			}catch(BlockStateDeserializeException $e){
				throw new SavedDataLoadingException("Error deserializing moving block: " . $e->getMessage(), 0, $e);
			}
		}
		$this->pistonPosition = new Vector3(
			$nbt->getInt(self::TAG_PISTON_X, $this->position->getFloorX()),
			$nbt->getInt(self::TAG_PISTON_Y, $this->position->getFloorY()),
			$nbt->getInt(self::TAG_PISTON_Z, $this->position->getFloorZ())
		);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		if($this->movingBlock !== null){
			$nbt->setTag(self::TAG_MOVING_BLOCK, GlobalBlockStateHandlers::getSerializer()->serialize($this->movingBlock->getStateId())->toNbt());
		}
		$piston = $this->getPistonPosition() ?? $this->position;
		$nbt->setInt(self::TAG_PISTON_X, $piston->getFloorX());
		$nbt->setInt(self::TAG_PISTON_Y, $piston->getFloorY());
		$nbt->setInt(self::TAG_PISTON_Z, $piston->getFloorZ());
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		if($this->movingBlock !== null){
			$nbt->setTag(self::TAG_MOVING_BLOCK, TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkStateData($this->movingBlock->getStateId())->toNbt());
		}
		$piston = $this->getPistonPosition() ?? $this->position;
		$nbt->setInt(self::TAG_PISTON_X, $piston->getFloorX());
		$nbt->setInt(self::TAG_PISTON_Y, $piston->getFloorY());
		$nbt->setInt(self::TAG_PISTON_Z, $piston->getFloorZ());
	}
}
