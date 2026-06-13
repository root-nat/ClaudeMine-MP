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

namespace pocketmine\world\generator\structure;

use pocketmine\utils\Random;
use pocketmine\world\generator\carver\GenerationVolume;

/**
 * A classic dungeon: a small cobblestone/mossy-cobblestone room with a hollow air interior, a monster-spawner block at
 * the centre and one or two chests against the walls. Geometry only - the spawner mob type and chest loot are tile NBT
 * that cannot be set in an async generator and must be filled by a main-thread step.
 */
final class DungeonStructure extends Structure{
	private const HEIGHT = 3;

	public function __construct(
		private int $airStateId,
		private int $cobblestoneStateId,
		private int $mossyCobblestoneStateId,
		private int $spawnerStateId,
		private int $chestStateId
	){}

	public function canPlace(GenerationVolume $volume, int $x, int $y, int $z) : bool{
		//need vertical room for the chamber and a solid floor under the anchor
		if(!$volume->isInBounds($x, $y - 1, $z) || !$volume->isInBounds($x, $y + self::HEIGHT + 1, $z)){
			return false;
		}
		if($volume->getBlockStateId($x, $y - 1, $z) === $this->airStateId){
			return false; //no floor to rest on
		}
		//require the chamber space to be at least partly open (cave-adjacent), so dungeons sit in caves
		$openCount = 0;
		for($dy = 0; $dy < self::HEIGHT; ++$dy){
			if($volume->getBlockStateId($x, $y + $dy, $z) === $this->airStateId){
				++$openCount;
			}
		}
		return $openCount > 0;
	}

	public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		$halfW = 2 + $random->nextBoundedInt(2); //footprint half-width 2 or 3 -> 5x5 or 7x7
		$halfD = 2 + $random->nextBoundedInt(2);

		$floorY = $y - 1;
		$ceilY = $y + self::HEIGHT;

		for($dx = -$halfW - 1; $dx <= $halfW + 1; ++$dx){
			for($dz = -$halfD - 1; $dz <= $halfD + 1; ++$dz){
				$bx = $x + $dx;
				$bz = $z + $dz;
				$onWallX = $dx === -$halfW - 1 || $dx === $halfW + 1;
				$onWallZ = $dz === -$halfD - 1 || $dz === $halfD + 1;

				//floor and ceiling slab
				$this->setMix($volume, $bx, $floorY, $bz, $random);
				$this->set($volume, $bx, $ceilY, $bz, $this->cobblestoneStateId);

				for($cy = $y; $cy < $ceilY; ++$cy){
					if($onWallX || $onWallZ){
						$this->setMix($volume, $bx, $cy, $bz, $random);
					}else{
						$this->set($volume, $bx, $cy, $bz, $this->airStateId);
					}
				}
			}
		}

		//spawner at the chamber centre
		$this->set($volume, $x, $y, $z, $this->spawnerStateId);

		//one or two chests against the interior of a wall
		$chestCount = 1 + $random->nextBoundedInt(2);
		for($i = 0; $i < $chestCount; ++$i){
			$cx = $x + ($random->nextBoolean() ? $halfW : -$halfW);
			$cz = $z + $random->nextBoundedInt($halfD * 2 + 1) - $halfD;
			if($cx === $x && $cz === $z){
				continue;
			}
			$this->set($volume, $cx, $y, $cz, $this->chestStateId);
		}
	}

	private function set(GenerationVolume $volume, int $x, int $y, int $z, int $stateId) : void{
		if($volume->isInBounds($x, $y, $z)){
			$volume->setBlockStateId($x, $y, $z, $stateId);
		}
	}

	private function setMix(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		$this->set($volume, $x, $y, $z, $random->nextBoundedInt(4) === 0 ? $this->mossyCobblestoneStateId : $this->cobblestoneStateId);
	}
}
