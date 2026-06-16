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
use pocketmine\world\generator\object\NetherTree;

/**
 * Decorates a Nether forest over its nylium: small flora (fungi, roots and - in the warped forest - nether sprouts),
 * occasional huge fungi (the stem-and-cap "trees" with shroomlight and, in the crimson forest, weeping vines), and - in
 * the warped forest - the odd cluster of twisting vines climbing up off the floor. The nylium surface itself is laid by
 * the generator; this only decorates it.
 */
class NetherForestPopulator implements Populator{

	private const HUGE_FUNGUS_MIN_HEIGHT = 5;

	public function __construct(
		private Block $fungus,
		private Block $roots,
		private ?Block $sprouts,
		private int $attempts,
		private ?Block $hugeFungusStem = null,
		private ?Block $hugeFungusHat = null,
		private bool $hugeFungusWeeps = false,
		private ?Block $climbingVine = null
	){}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$baseX = $chunkX * Chunk::EDGE_LENGTH;
		$baseZ = $chunkZ * Chunk::EDGE_LENGTH;

		for($i = 0; $i < $this->attempts; ++$i){
			$x = $baseX + $random->nextRange(0, Chunk::EDGE_LENGTH - 1);
			$z = $baseZ + $random->nextRange(0, Chunk::EDGE_LENGTH - 1);
			$y = $this->findNyliumSurface($world, $x, $z, $random);
			if($y === -1){
				continue;
			}

			$roll = $random->nextRange(0, 11);
			if($roll === 0){
				$block = $this->fungus;
			}elseif($this->sprouts !== null && $roll <= 3){
				$block = $this->sprouts;
			}else{
				$block = $this->roots;
			}
			$world->setBlockAt($x, $y, $z, $block);
		}

		if($this->hugeFungusStem !== null && $this->hugeFungusHat !== null){
			$hugeFungi = $random->nextRange(1, 4);
			for($i = 0; $i < $hugeFungi; ++$i){
				$x = $baseX + $random->nextRange(0, Chunk::EDGE_LENGTH - 1);
				$z = $baseZ + $random->nextRange(0, Chunk::EDGE_LENGTH - 1);
				$y = $this->findNyliumSurface($world, $x, $z, $random);
				if($y === -1){
					continue;
				}
				$height = self::HUGE_FUNGUS_MIN_HEIGHT + $random->nextBoundedInt(8); //5..12
				$huge = $random->nextBoundedInt(4) === 0; //one in four is the bigger 3x3-stemmed variant
				$fungus = new NetherTree($this->hugeFungusStem, $this->hugeFungusHat, VanillaBlocks::SHROOMLIGHT(), $height, $this->hugeFungusWeeps, $huge);
				$fungus->getBlockTransaction($world, $x, $y, $z, $random)?->apply();
			}
		}

		if($this->climbingVine !== null){
			$clusters = $random->nextRange(0, 4);
			for($i = 0; $i < $clusters; ++$i){
				$x = $baseX + $random->nextRange(0, Chunk::EDGE_LENGTH - 1);
				$z = $baseZ + $random->nextRange(0, Chunk::EDGE_LENGTH - 1);
				$y = $this->findNyliumSurface($world, $x, $z, $random);
				if($y === -1){
					continue;
				}
				$length = 1 + $random->nextBoundedInt(4); //1..4 tall
				for($h = 0; $h < $length; ++$h){
					if($world->getBlockAt($x, $y + $h, $z)->getTypeId() !== BlockTypeIds::AIR){
						break;
					}
					$world->setBlockAt($x, $y + $h, $z, $this->climbingVine);
				}
			}
		}
	}

	/**
	 * Scans down for an air block sitting on nylium or netherrack (a forest floor) and returns that air Y, or -1.
	 */
	private function findNyliumSurface(ChunkManager $world, int $x, int $z, Random $random) : int{
		for($y = $random->nextRange(34, 120); $y > 32; --$y){
			if($world->getBlockAt($x, $y, $z)->getTypeId() !== BlockTypeIds::AIR){
				continue;
			}
			$below = $world->getBlockAt($x, $y - 1, $z)->getTypeId();
			if($below === BlockTypeIds::CRIMSON_NYLIUM || $below === BlockTypeIds::WARPED_NYLIUM || $below === BlockTypeIds::NETHERRACK){
				return $y;
			}
		}
		return -1;
	}
}
