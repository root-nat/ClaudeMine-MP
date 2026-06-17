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
use pocketmine\entity\Villager;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

/**
 * Main-thread companion to the village {@link JigsawStructure}: it re-derives each village's anchor through the SHARED
 * {@link SurfaceStructurePopulator::forEachOrigin} (same SALT/RARITY/MAX_RADIUS the populator used), recovers the same
 * ground Y with the same {@link SurfaceScan} on the live world, then RE-RUNS the deterministic jigsaw assembly with the
 * same seeded Random to recover every piece. Each piece's furnishing markers (a pure function of the assembly) give the
 * world positions of chests to fill and villagers to spawn - so loot and mobs land exactly inside the pieces the async
 * generator placed, with nothing persisted between the two threads. Registered for 'normal'/'default' in {@link
 * ChunkFurnisherRegistry}.
 */
final class VillageFurnisher implements ChunkFurnisher{

	public function furnishChunk(World $world, int $chunkX, int $chunkZ, Chunk $chunk) : void{
		$minX = $chunkX << Chunk::COORD_BIT_SIZE;
		$minZ = $chunkZ << Chunk::COORD_BIT_SIZE;

		SurfaceStructurePopulator::forEachOrigin($world->getSeed(), $chunkX, $chunkZ, VillageStructure::SALT, VillageStructure::RARITY, VillageStructure::MAX_RADIUS, function(int $ax, int $az, Random $random) use ($world, $minX, $minZ) : void{
			$groundY = SurfaceScan::topSolidY(fn(int $y) : int => $world->getBlockAt($ax, $y, $az)->getStateId(), VillageStructure::SURFACE_TOP_Y, VillageStructure::SURFACE_MIN_Y);
			if($groundY === null){
				return; //no recoverable surface at the anchor column - this origin did not place here
			}
			//re-run the IDENTICAL assembly the generator ran (same seeded Random) to recover piece/marker positions. The
			//palette only changes block ids, never geometry/markers, so any palette recovers the same positions.
			$village = new VillageStructure(VillagePalette::plains());
			foreach($village->assemblePieces($random) as $piece){
				foreach($piece->worldMarkers() as [$lx, $ly, $lz, $type]){
					$x = $ax + $lx;
					$y = $groundY + $ly;
					$z = $az + $lz;
					if($type === "chest"){
						$this->furnishChest($world, $minX, $minZ, $x, $y, $z);
					}elseif($type === "villager"){
						$this->spawnVillager($world, $minX, $minZ, $x, $y, $z);
					}
				}
			}
		});
	}

	private function furnishChest(World $world, int $minX, int $minZ, int $x, int $y, int $z) : void{
		if(!self::inChunk($minX, $minZ, $x, $z)){
			return; //a neighbouring chunk owns this point and will furnish it
		}
		if($world->getBlockAt($x, $y, $z)->getTypeId() !== BlockTypeIds::CHEST){
			return; //the chest block isn't here (clipped / overwritten) - nothing to fill
		}
		if($world->getTileAt($x, $y, $z) instanceof TileChest){
			return; //already furnished
		}

		$tile = new TileChest($world, new Vector3($x, $y, $z));
		$inventory = $tile->getRealInventory();
		$random = new Random($world->getSeed() ^ ($x * 31 + $z * 17 + $y) ^ VillageStructure::SALT);
		$size = $inventory->getSize();
		foreach(VillageLoot::roll($random) as $item){
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

	private function spawnVillager(World $world, int $minX, int $minZ, int $x, int $y, int $z) : void{
		if(!self::inChunk($minX, $minZ, $x, $z)){
			return; //a neighbouring chunk owns this point
		}
		//Only spawn on an actual house floor. forEachOrigin re-derives EVERY region anchor, including ones the generator
		//skipped (wrong biome / no surface), so without this structural gate a lone villager could spawn on bare ground in
		//the wild. A house always floors with its palette's floor block, so its presence proves a house is here - this is
		//the villager analogue of the chest's "block must be a CHEST" guard, and it also skips clipped pieces.
		if(!VillagePalette::isHouseFloor($world->getBlockAt($x, $y - 1, $z)->getTypeId())){
			return;
		}
		//two clear cells above the floor, so the villager doesn't spawn buried in a wall
		if($world->getBlockAt($x, $y, $z)->isSolid() || $world->getBlockAt($x, $y + 1, $z)->isSolid()){
			return;
		}
		//a Villager is not a Monster, so it is not subject to the hostile-mob distance despawn - no setPersistent() needed
		$villager = new Villager(Location::fromObject(new Vector3($x + 0.5, (float) $y, $z + 0.5), $world));
		$villager->spawnToAll();
	}

	private static function inChunk(int $minX, int $minZ, int $x, int $z) : bool{
		return $x >= $minX && $x < $minX + Chunk::EDGE_LENGTH && $z >= $minZ && $z < $minZ + Chunk::EDGE_LENGTH;
	}
}
