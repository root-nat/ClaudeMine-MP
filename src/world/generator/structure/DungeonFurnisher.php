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
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

/**
 * Main-thread companion to the cave {@link DungeonStructure}: it fills the loot chests the async generator could only
 * place as bare block states (the spawner's mob is configured by the spawner block itself on first tick, so only the
 * chests need filling). Registered for the 'normal'/'default' generators in {@link ChunkFurnisherRegistry}.
 *
 * A dungeon's anchor cannot be recovered by re-running the cave-floor scan, because the placed spawner breaks the
 * air-over-solid test at the anchor column. Instead it replays the SHARED {@link StructurePopulator::forEachRegionCandidate}
 * to recover the same candidate (x, z) columns, and confirms a dungeon by finding the persistent MONSTER_SPAWNER block in
 * that column (its Y is the chamber centre). It then fills every chest within the small chamber footprint. Every chest
 * point is guarded so a chunk furnishes only its own points, and loot is seeded from the chest position so it is
 * identical no matter which neighbouring chunk's pass fills it.
 */
final class DungeonFurnisher implements ChunkFurnisher{

	private const CHAMBER_RADIUS = 3; //the dungeon's half-width is at most 3, so chests lie within +-3 of the spawner

	public function furnishChunk(World $world, int $chunkX, int $chunkZ, Chunk $chunk) : void{
		$minX = $chunkX << Chunk::COORD_BIT_SIZE;
		$minZ = $chunkZ << Chunk::COORD_BIT_SIZE;

		StructurePopulator::forEachRegionCandidate(
			$world->getSeed(), $chunkX, $chunkZ,
			StructurePopulator::DEFAULT_RARITY, StructurePopulator::DEFAULT_MIN_ANCHOR_Y, StructurePopulator::DEFAULT_MAX_ANCHOR_Y,
			$world->getMinY(), $world->getMaxY(),
			function(int $x, int $z, int $startY, int $lowY, int $highY, Random $random) use ($world, $minX, $minZ) : bool{
				$spawnerY = $this->findSpawner($world, $x, $z, $lowY, $highY);
				if($spawnerY !== null){
					$this->furnishChamber($world, $minX, $minZ, $x, $spawnerY, $z);
				}
				//never stop early: a foreign spawner in one candidate column must not hide this region's real dungeon in a
				//later candidate. Filling is idempotent and position-seeded, so visiting extra candidates is harmless.
				return false;
			}
		);
	}

	private function findSpawner(World $world, int $x, int $z, int $lowY, int $highY) : ?int{
		for($y = $lowY; $y <= $highY; ++$y){
			if($world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::MONSTER_SPAWNER){
				return $y;
			}
		}
		return null;
	}

	private function furnishChamber(World $world, int $minX, int $minZ, int $cx, int $cy, int $cz) : void{
		//chests sit on the chamber floor at the spawner's Y, within the chamber footprint
		for($dx = -self::CHAMBER_RADIUS; $dx <= self::CHAMBER_RADIUS; ++$dx){
			for($dz = -self::CHAMBER_RADIUS; $dz <= self::CHAMBER_RADIUS; ++$dz){
				$this->furnishChest($world, $minX, $minZ, $cx + $dx, $cy, $cz + $dz);
			}
		}
	}

	private function furnishChest(World $world, int $minX, int $minZ, int $x, int $y, int $z) : void{
		if(!self::inChunk($minX, $minZ, $x, $z)){
			return; //a neighbouring chunk owns this point and will furnish it
		}
		if($world->getBlockAt($x, $y, $z)->getTypeId() !== BlockTypeIds::CHEST){
			return; //not a dungeon chest cell
		}
		if($world->getTileAt($x, $y, $z) instanceof TileChest){
			return; //already furnished
		}

		$tile = new TileChest($world, new Vector3($x, $y, $z));
		$inventory = $tile->getRealInventory();
		$random = new Random($world->getSeed() ^ ($x * 31 + $z * 17 + $y) ^ StructurePopulator::SALT);
		$size = $inventory->getSize();
		foreach(DungeonLoot::roll($random) as $item){
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

	private static function inChunk(int $minX, int $minZ, int $x, int $z) : bool{
		return $x >= $minX && $x < $minX + Chunk::EDGE_LENGTH && $z >= $minZ && $z < $minZ + Chunk::EDGE_LENGTH;
	}
}
