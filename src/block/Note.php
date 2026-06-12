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
use pocketmine\block\utils\WoodMaterial;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\NoteInstrument;
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

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		$this->pitch = $this->pitch >= self::MAX_PITCH ? self::MIN_PITCH : $this->pitch + 1;
		$this->position->getWorld()->setBlock($this->position, $this);
		$this->playNote();
		return true;
	}

	public function onNearbyBlockChange() : void{
		foreach(Facing::ALL as $face){
			$neighbor = $this->getSide($face);
			if($neighbor->getWeakRedstonePower(Facing::opposite($face)) > 0 || $neighbor->getStrongRedstonePower(Facing::opposite($face)) > 0){
				$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 1);
				return;
			}
		}
	}

	public function onScheduledUpdate() : void{
		foreach(Facing::ALL as $face){
			$neighbor = $this->getSide($face);
			if($neighbor->getWeakRedstonePower(Facing::opposite($face)) > 0 || $neighbor->getStrongRedstonePower(Facing::opposite($face)) > 0){
				$this->playNote();
				return;
			}
		}
	}

	private function playNote() : void{
		$this->position->getWorld()->addSound($this->position->add(0.5, 0.5, 0.5), new NoteSound($this->getInstrument(), $this->pitch));
	}

	private function getInstrument() : NoteInstrument{
		$below = $this->getSide(Facing::DOWN);
		return match(true){
			$below->getTypeId() === BlockTypeIds::SAND || $below->getTypeId() === BlockTypeIds::RED_SAND || $below->getTypeId() === BlockTypeIds::GRAVEL => NoteInstrument::SNARE,
			$below->getTypeId() === BlockTypeIds::GOLD => NoteInstrument::BELL,
			$below->getTypeId() === BlockTypeIds::CLAY || $below->getTypeId() === BlockTypeIds::STAINED_CLAY || $below->getTypeId() === BlockTypeIds::HARDENED_CLAY => NoteInstrument::FLUTE,
			$below->getTypeId() === BlockTypeIds::PACKED_ICE => NoteInstrument::CHIME,
			$below->getTypeId() === BlockTypeIds::WOOL => NoteInstrument::GUITAR,
			$below->getTypeId() === BlockTypeIds::BONE_BLOCK => NoteInstrument::XYLOPHONE,
			$below->getTypeId() === BlockTypeIds::IRON => NoteInstrument::IRON_XYLOPHONE,
			$below->getTypeId() === BlockTypeIds::SOUL_SAND => NoteInstrument::COW_BELL,
			$below->getTypeId() === BlockTypeIds::PUMPKIN || $below->getTypeId() === BlockTypeIds::CARVED_PUMPKIN || $below->getTypeId() === BlockTypeIds::LIT_PUMPKIN => NoteInstrument::DIDGERIDOO,
			$below->getTypeId() === BlockTypeIds::EMERALD => NoteInstrument::BIT,
			$below->getTypeId() === BlockTypeIds::HAY_BALE => NoteInstrument::BANJO,
			$below->getTypeId() === BlockTypeIds::GLOWSTONE => NoteInstrument::PLING,
			$below instanceof WoodMaterial => NoteInstrument::DOUBLE_BASS,
			$below->isSolid() => NoteInstrument::BASS_DRUM,
			default => NoteInstrument::PIANO,
		};
	}
}
