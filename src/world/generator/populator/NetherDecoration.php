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

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

/**
 * Scatters the Nether wastes' ambient decoration over a generated chunk: glowstone blobs hanging from cave ceilings and
 * patches of fire burning on the netherrack floor. The Nether has no flat surface, so each is found by scanning a column
 * for the netherrack/air transitions that mark a cave roof or floor.
 */
class NetherDecoration implements Populator{
	/** Glowstone-blob attempts per chunk. */
	private const GLOWSTONE_ATTEMPTS = 1;
	/** Random-walk placements making up one glowstone blob. */
	private const GLOWSTONE_BLOB_SIZE = 120;
	private const FIRE_ATTEMPTS = 6;

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$baseX = $chunkX * Chunk::EDGE_LENGTH;
		$baseZ = $chunkZ * Chunk::EDGE_LENGTH;

		$glowstone = VanillaBlocks::GLOWSTONE();
		for($i = 0; $i < self::GLOWSTONE_ATTEMPTS; ++$i){
			$x = $baseX + $random->nextRange(0, Chunk::EDGE_LENGTH - 1);
			$z = $baseZ + $random->nextRange(0, Chunk::EDGE_LENGTH - 1);
			$y = $this->findCaveCeiling($world, $x, $z, $random);
			if($y !== -1){
				$this->growGlowstone($world, $x, $y, $z, $random, $glowstone);
			}
		}

		$fire = VanillaBlocks::FIRE();
		for($i = 0; $i < self::FIRE_ATTEMPTS; ++$i){
			$x = $baseX + $random->nextRange(0, Chunk::EDGE_LENGTH - 1);
			$z = $baseZ + $random->nextRange(0, Chunk::EDGE_LENGTH - 1);
			$y = $random->nextRange(5, 120);
			if($world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::AIR && $world->getBlockAt($x, $y - 1, $z)->getTypeId() === BlockTypeIds::NETHERRACK){
				$world->setBlockAt($x, $y, $z, $fire);
			}
		}
	}

	/**
	 * Scans down from a random upper point for netherrack with air directly below it - a cave roof - and returns that air
	 * block's Y (where the glowstone hangs), or -1 if none found.
	 */
	private function findCaveCeiling(ChunkManager $world, int $x, int $z, Random $random) : int{
		for($y = $random->nextRange(70, 120); $y > 33; --$y){
			if($world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::NETHERRACK && $world->getBlockAt($x, $y - 1, $z)->getTypeId() === BlockTypeIds::AIR){
				return $y - 1;
			}
		}
		return -1;
	}

	private function growGlowstone(ChunkManager $world, int $x, int $y, int $z, Random $random, Block $glowstone) : void{
		$world->setBlockAt($x, $y, $z, $glowstone);
		for($i = 0; $i < self::GLOWSTONE_BLOB_SIZE; ++$i){
			$bx = $x + $random->nextRange(-2, 2);
			$by = $y - $random->nextRange(0, 4);
			$bz = $z + $random->nextRange(-2, 2);
			if($world->getBlockAt($bx, $by, $bz)->getTypeId() !== BlockTypeIds::AIR){
				continue;
			}
			//only grow off existing glowstone or the netherrack ceiling so the blob hangs instead of floating in mid-air
			$anchored = false;
			foreach([[0, 1, 0], [0, -1, 0], [1, 0, 0], [-1, 0, 0], [0, 0, 1], [0, 0, -1]] as [$ox, $oy, $oz]){
				$neighbour = $world->getBlockAt($bx + $ox, $by + $oy, $bz + $oz)->getTypeId();
				if($neighbour === BlockTypeIds::GLOWSTONE || $neighbour === BlockTypeIds::NETHERRACK){
					$anchored = true;
					break;
				}
			}
			if($anchored){
				$world->setBlockAt($bx, $by, $bz, $glowstone);
			}
		}
	}
}
