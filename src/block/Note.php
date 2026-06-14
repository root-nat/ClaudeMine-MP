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

use pocketmine\block\tile\Note as TileNote;
use pocketmine\block\utils\NoteBlockInstruments;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\NoteSound;
use function assert;

class Note extends Opaque{
	public const MIN_PITCH = 0;
	public const MAX_PITCH = 24;

	private int $pitch = self::MIN_PITCH;

	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();
		$tile = $this->position->getWorld()->getTile($this->position);
		if($tile instanceof TileNote){
			$this->pitch = $tile->getPitch();
		}else{
			$this->pitch = self::MIN_PITCH;
		}

		return $this;
	}

	public function writeStateToWorld() : void{
		parent::writeStateToWorld();
		$tile = $this->position->getWorld()->getTile($this->position);
		assert($tile instanceof TileNote);
		$tile->setPitch($this->pitch);
	}

	public function getFuelTime() : int{
		return 300;
	}

	public function getPitch() : int{
		return $this->pitch;
	}

	/** @return $this */
	public function setPitch(int $pitch) : self{
		if($pitch < self::MIN_PITCH || $pitch > self::MAX_PITCH){
			throw new \InvalidArgumentException("Pitch must be in range " . self::MIN_PITCH . " - " . self::MAX_PITCH);
		}
		$this->pitch = $pitch;
		return $this;
	}

	private function playNote() : void{
		$world = $this->position->getWorld();
		$instrument = NoteBlockInstruments::fromBlockBelow($this->getSide(Facing::DOWN));
		$world->addSound($this->position->add(0.5, 0.5, 0.5), new NoteSound($instrument, $this->pitch));
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		$this->pitch = ($this->pitch + 1) % (self::MAX_PITCH + 1);
		$this->position->getWorld()->setBlock($this->position, $this);
		$this->playNote();
		return true;
	}

	public function onNearbyBlockChange() : void{
		$tile = $this->position->getWorld()->getTile($this->position);
		if(!$tile instanceof TileNote){
			return;
		}
		//play once on a rising redstone edge; the previous powered state is persisted in the tile (not a block state, so
		//it doesn't affect the Bedrock serialization), letting us tell a fresh power signal from an unrelated neighbour change
		$powered = $this->isReceivingRedstonePower();
		if($powered === $tile->isPowered()){
			return;
		}
		$tile->setPowered($powered);
		if($powered){
			$this->playNote();
		}
	}
}
