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
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

/**
 * Scatters a Nether forest's small flora (fungi, roots and - in the warped forest - nether sprouts) over the nylium
 * surfaces of a chunk. The plant placed is weighted: mostly roots, some sprouts, the occasional fungus. The surface
 * itself (the nylium) is laid by the generator; this only decorates it.
 */
class NetherForestPopulator implements Populator{

	public function __construct(
		private Block $fungus,
		private Block $roots,
		private ?Block $sprouts,
		private int $attempts
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
