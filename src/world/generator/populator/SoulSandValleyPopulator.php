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
 * Decorates the soul sand valley: patches of blue soul fire flickering over the soul soil/sand, and the occasional
 * half-buried bone fossil.
 */
class SoulSandValleyPopulator implements Populator{

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$baseX = $chunkX * Chunk::EDGE_LENGTH;
		$baseZ = $chunkZ * Chunk::EDGE_LENGTH;

		$fires = $random->nextRange(0, 6);
		for($i = 0; $i < $fires; ++$i){
			$x = $baseX + $random->nextRange(0, Chunk::EDGE_LENGTH - 1);
			$z = $baseZ + $random->nextRange(0, Chunk::EDGE_LENGTH - 1);
			$y = $this->findSoulSurface($world, $x, $z, $random);
			if($y !== -1){
				$world->setBlockAt($x, $y, $z, VanillaBlocks::SOUL_FIRE());
			}
		}

		//a fossil now and then, kept off the chunk edges so it isn't clipped
		if($random->nextBoundedInt(10) === 0){
			$x = $baseX + $random->nextRange(2, Chunk::EDGE_LENGTH - 3);
			$z = $baseZ + $random->nextRange(2, Chunk::EDGE_LENGTH - 3);
			$y = $this->findSoulSurface($world, $x, $z, $random);
			if($y !== -1){
				$this->placeFossil($world, $x, $y - 1, $z, $random);
			}
		}
	}

	/**
	 * Scans down for an air block resting on soul soil or soul sand (so soul fire actually persists), returning that air
	 * Y or -1.
	 */
	private function findSoulSurface(ChunkManager $world, int $x, int $z, Random $random) : int{
		for($y = $random->nextRange(34, 118); $y > 32; --$y){
			if($world->getBlockAt($x, $y, $z)->getTypeId() !== BlockTypeIds::AIR){
				continue;
			}
			$below = $world->getBlockAt($x, $y - 1, $z)->getTypeId();
			if($below === BlockTypeIds::SOUL_SOIL || $below === BlockTypeIds::SOUL_SAND){
				return $y;
			}
		}
		return -1;
	}

	/**
	 * A short bone-block spine half-buried at $y with the odd rib poking up to the surface - a small fossil.
	 */
	private function placeFossil(ChunkManager $world, int $x, int $y, int $z, Random $random) : void{
		$alongX = $random->nextBoolean();
		$length = 3 + $random->nextBoundedInt(4); //3..6 segments
		$spineAxis = $alongX ? Axis::X : Axis::Z;
		for($s = 0; $s < $length; ++$s){
			$sx = $alongX ? $x + $s : $x;
			$sz = $alongX ? $z : $z + $s;
			$world->setBlockAt($sx, $y, $sz, VanillaBlocks::BONE_BLOCK()->setAxis($spineAxis));
			if($s % 2 === 1){
				$world->setBlockAt($sx, $y + 1, $sz, VanillaBlocks::BONE_BLOCK()->setAxis(Axis::Y));
			}
		}
	}
}
