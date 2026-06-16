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

use pocketmine\block\BlockTypeIds;
use pocketmine\block\tile\Chest as TileChest;
use pocketmine\entity\Location;
use pocketmine\entity\Piglin;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

/**
 * Main-thread companion to {@link NetherBastionPopulator}: it re-derives the same bastion anchors (so it agrees with the
 * async block placement) and, for the chest and piglin points that fall inside the just-populated chunk, fills the loot
 * chest's inventory and spawns the guarding piglins - neither of which the async generator can do. Registered for the
 * 'nether' generator in {@link ChunkFurnisherRegistry} and invoked once per populated chunk by the World.
 */
final class NetherBastionFurnisher implements ChunkFurnisher{

	public function furnishChunk(World $world, int $chunkX, int $chunkZ, Chunk $chunk) : void{
		$minX = $chunkX << Chunk::COORD_BIT_SIZE;
		$minZ = $chunkZ << Chunk::COORD_BIT_SIZE;

		NetherBastionPopulator::forEachOrigin($world->getSeed(), $chunkX, $chunkZ, function(int $ax, int $ay, int $az, Random $random) use ($world, $minX, $minZ) : void{
			[$cdx, $cdy, $cdz] = NetherBastionStructure::CHEST_OFFSET;
			$this->furnishChest($world, $minX, $minZ, $ax + $cdx, $ay + $cdy, $az + $cdz);
			foreach(NetherBastionStructure::PIGLIN_OFFSETS as [$pdx, $pdy, $pdz]){
				$this->spawnPiglin($world, $minX, $minZ, $ax + $pdx, $ay + $pdy, $az + $pdz);
			}
		});
	}

	private function furnishChest(World $world, int $minX, int $minZ, int $x, int $y, int $z) : void{
		if(!self::inChunk($minX, $minZ, $x, $z)){
			return; //a neighbouring chunk owns this point and will furnish it
		}
		if($world->getBlockAt($x, $y, $z)->getTypeId() !== BlockTypeIds::CHEST){
			return; //the structure block isn't here (overwritten / clipped) - nothing to fill
		}
		if($world->getTileAt($x, $y, $z) instanceof TileChest){
			return; //already furnished
		}

		$tile = new TileChest($world, new Vector3($x, $y, $z));
		$inventory = $tile->getRealInventory();
		$random = new Random($world->getSeed() ^ ($x * 31 + $z * 17 + $y) ^ 0x10f7);
		$size = $inventory->getSize();
		foreach(BastionLoot::roll($random) as $item){
			$slot = $random->nextBoundedInt($size);
			for($attempt = 0; $attempt < $size; ++$attempt){
				$candidate = ($slot + $attempt) % $size;
				if($inventory->getItem($candidate)->isNull()){
					$inventory->setItem($candidate, $item);
					break;
				}
			}
		}
		$world->addTile($tile);
	}

	private function spawnPiglin(World $world, int $minX, int $minZ, int $x, int $y, int $z) : void{
		if(!self::inChunk($minX, $minZ, $x, $z)){
			return;
		}
		//two clear cells over a solid floor, so the piglin doesn't spawn buried in a wall or over the void
		if($world->getBlockAt($x, $y, $z)->isSolid()
			|| $world->getBlockAt($x, $y + 1, $z)->isSolid()
			|| !$world->getBlockAt($x, $y - 1, $z)->isSolid()){
			return;
		}
		$piglin = new Piglin(Location::fromObject(new Vector3($x + 0.5, (float) $y, $z + 0.5), $world));
		$piglin->setPersistent();
		$piglin->spawnToAll();
	}

	private static function inChunk(int $minX, int $minZ, int $x, int $z) : bool{
		return $x >= $minX && $x < $minX + Chunk::EDGE_LENGTH && $z >= $minZ && $z < $minZ + Chunk::EDGE_LENGTH;
	}
}
