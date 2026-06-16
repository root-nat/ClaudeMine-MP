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

namespace pocketmine\world\generator\populator;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Axis;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

/**
 * Raises the basalt deltas' signature basalt columns: short vertical basalt pillars rising from the floor, some tipped
 * with magma.
 */
class BasaltColumnPopulator implements Populator{

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$baseX = $chunkX * Chunk::EDGE_LENGTH;
		$baseZ = $chunkZ * Chunk::EDGE_LENGTH;

		$columns = $random->nextRange(0, 4);
		for($i = 0; $i < $columns; ++$i){
			$x = $baseX + $random->nextRange(0, Chunk::EDGE_LENGTH - 1);
			$z = $baseZ + $random->nextRange(0, Chunk::EDGE_LENGTH - 1);
			$y = $this->findFloor($world, $x, $z, $random);
			if($y === -1){
				continue;
			}

			$height = 2 + $random->nextBoundedInt(7); //2..8
			$basalt = VanillaBlocks::BASALT()->setAxis(Axis::Y);
			$placed = 0;
			for($h = 0; $h < $height; ++$h){
				if($world->getBlockAt($x, $y + $h, $z)->getTypeId() !== BlockTypeIds::AIR){
					break;
				}
				$world->setBlockAt($x, $y + $h, $z, $basalt);
				++$placed;
			}
			//a quarter of the full-height columns are tipped with magma
			if($placed === $height && $random->nextBoundedInt(4) === 0 && $world->getBlockAt($x, $y + $placed, $z)->getTypeId() === BlockTypeIds::AIR){
				$world->setBlockAt($x, $y + $placed, $z, VanillaBlocks::MAGMA());
			}
		}
	}

	/**
	 * Scans down for an air block resting on any solid floor (basalt/blackstone terrain), returning that air Y or -1.
	 */
	private function findFloor(ChunkManager $world, int $x, int $z, Random $random) : int{
		for($y = $random->nextRange(34, 118); $y > 32; --$y){
			if($world->getBlockAt($x, $y, $z)->getTypeId() !== BlockTypeIds::AIR){
				continue;
			}
			if($world->getBlockAt($x, $y - 1, $z)->isSolid()){
				return $y;
			}
		}
		return -1;
	}
}
