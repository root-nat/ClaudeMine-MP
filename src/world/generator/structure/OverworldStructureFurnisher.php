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
use pocketmine\entity\Pillager;
use pocketmine\entity\Witch;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

/**
 * Main-thread companion to the overworld {@link SurfaceStructurePopulator}s: it re-derives the same structure anchors
 * (via the SHARED {@link SurfaceStructurePopulator::forEachOrigin}) and fills the loot chests the async generator could
 * only place as bare block states. Registered for the 'normal'/'default' generators in {@link ChunkFurnisherRegistry}.
 *
 * Because a surface structure never builds over its anchor column, this re-runs the IDENTICAL {@link SurfaceScan} on the
 * live world to recover the same groundY the populator used, then computes each chest position as anchor + a FIXED
 * offset - exactly the {@link NetherBastionFurnisher} pattern, but with the anchor Y recovered by a surface scan instead
 * of a constant deck height.
 */
final class OverworldStructureFurnisher implements ChunkFurnisher{

	/**
	 * @var array<int, array{salt: int, rarity: int, maxRadius: int, surfaceTopY: int, surfaceMinY: int, chestOffsets: list<array{int, int, int}>, loot: string, mobOffsets: list<array{int, int, int, string}>}>
	 */
	private const SPECS = [
		[
			"salt" => DesertTempleStructure::SALT,
			"rarity" => DesertTempleStructure::RARITY,
			"maxRadius" => DesertTempleStructure::MAX_RADIUS,
			"surfaceTopY" => DesertTempleStructure::SURFACE_TOP_Y,
			"surfaceMinY" => DesertTempleStructure::SURFACE_MIN_Y,
			"chestOffsets" => DesertTempleStructure::CHEST_OFFSETS,
			"loot" => "desert_temple",
			"mobOffsets" => []
		],
		[
			"salt" => WitchHutStructure::SALT,
			"rarity" => WitchHutStructure::RARITY,
			"maxRadius" => WitchHutStructure::MAX_RADIUS,
			"surfaceTopY" => 120,
			"surfaceMinY" => 40,
			"chestOffsets" => [],
			"loot" => "",
			//[dx, dy, dz, mob]; the coords are WitchHutStructure::WITCH_OFFSET ([5, 3, 0]) - a witch stands on the hut deck
			"mobOffsets" => [[5, 3, 0, "witch"]]
		],
		[
			"salt" => JungleTempleStructure::SALT,
			"rarity" => JungleTempleStructure::RARITY,
			"maxRadius" => JungleTempleStructure::MAX_RADIUS,
			"surfaceTopY" => 120,
			"surfaceMinY" => 40,
			"chestOffsets" => JungleTempleStructure::CHEST_OFFSETS,
			"loot" => "jungle_temple",
			"mobOffsets" => []
		],
		[
			"salt" => PillagerOutpostStructure::SALT,
			"rarity" => PillagerOutpostStructure::RARITY,
			"maxRadius" => PillagerOutpostStructure::MAX_RADIUS,
			"surfaceTopY" => 120,
			"surfaceMinY" => 40,
			"chestOffsets" => [PillagerOutpostStructure::CHEST_OFFSET],
			"loot" => "pillager_outpost",
			//= PillagerOutpostStructure::MOB_OFFSETS ([[5,16,1],[3,16,-1]]) + the mob type
			"mobOffsets" => [[5, 16, 1, "pillager"], [3, 16, -1, "pillager"]]
		],
		[
			"salt" => IglooStructure::SALT,
			"rarity" => IglooStructure::RARITY,
			"maxRadius" => IglooStructure::MAX_RADIUS,
			"surfaceTopY" => 120,
			"surfaceMinY" => 40,
			"chestOffsets" => [IglooStructure::CHEST_OFFSET],
			"loot" => "igloo",
			"mobOffsets" => []
		],
		[
			"salt" => RuinedPortalStructure::SALT,
			"rarity" => RuinedPortalStructure::RARITY,
			"maxRadius" => RuinedPortalStructure::MAX_RADIUS,
			"surfaceTopY" => 120,
			"surfaceMinY" => 40,
			"chestOffsets" => [RuinedPortalStructure::CHEST_OFFSET],
			"loot" => "ruined_portal",
			"mobOffsets" => []
		],
		[
			"salt" => ShipwreckStructure::SALT,
			"rarity" => ShipwreckStructure::RARITY,
			"maxRadius" => ShipwreckStructure::MAX_RADIUS,
			"surfaceTopY" => 120,
			"surfaceMinY" => 30, //ocean floor sits well below sea level
			"chestOffsets" => ShipwreckStructure::CHEST_OFFSETS,
			"loot" => "shipwreck",
			"mobOffsets" => []
		],
		[
			"salt" => BuriedTreasureStructure::SALT,
			"rarity" => BuriedTreasureStructure::RARITY,
			"maxRadius" => BuriedTreasureStructure::MAX_RADIUS,
			"surfaceTopY" => 120,
			"surfaceMinY" => 30,
			"chestOffsets" => [BuriedTreasureStructure::CHEST_OFFSET],
			"loot" => "buried_treasure",
			"mobOffsets" => []
		],
		[
			"salt" => OceanRuinsStructure::SALT,
			"rarity" => OceanRuinsStructure::RARITY,
			"maxRadius" => OceanRuinsStructure::MAX_RADIUS,
			"surfaceTopY" => 120,
			"surfaceMinY" => 30,
			"chestOffsets" => [OceanRuinsStructure::CHEST_OFFSET],
			"loot" => "ocean_ruins",
			"mobOffsets" => []
		]
	];

	public function furnishChunk(World $world, int $chunkX, int $chunkZ, Chunk $chunk) : void{
		$minX = $chunkX << Chunk::COORD_BIT_SIZE;
		$minZ = $chunkZ << Chunk::COORD_BIT_SIZE;

		foreach(self::SPECS as $spec){
			SurfaceStructurePopulator::forEachOrigin($world->getSeed(), $chunkX, $chunkZ, $spec["salt"], $spec["rarity"], $spec["maxRadius"], function(int $ax, int $az, Random $random) use ($world, $minX, $minZ, $spec) : void{
				$groundY = SurfaceScan::topSolidY(fn(int $y) : int => $world->getBlockAt($ax, $y, $az)->getStateId(), $spec["surfaceTopY"], $spec["surfaceMinY"]);
				if($groundY === null){
					return; //no recoverable surface at the anchor column
				}
				foreach($spec["chestOffsets"] as [$ox, $oy, $oz]){
					$this->furnishChest($world, $minX, $minZ, $ax + $ox, $groundY + $oy, $az + $oz, $spec["loot"], $spec["salt"]);
				}
				foreach($spec["mobOffsets"] as [$ox, $oy, $oz, $mob]){
					$this->spawnMob($world, $minX, $minZ, $ax + $ox, $groundY + $oy, $az + $oz, $mob);
				}
			});
		}
	}

	private function furnishChest(World $world, int $minX, int $minZ, int $x, int $y, int $z, string $lootType, int $salt) : void{
		if(!self::inChunk($minX, $minZ, $x, $z)){
			return; //a neighbouring chunk owns this point and will furnish it
		}
		if($world->getBlockAt($x, $y, $z)->getTypeId() !== BlockTypeIds::CHEST){
			return; //the structure block isn't here (clipped / overwritten) - nothing to fill
		}
		if($world->getTileAt($x, $y, $z) instanceof TileChest){
			return; //already furnished
		}

		$tile = new TileChest($world, new Vector3($x, $y, $z));
		$inventory = $tile->getRealInventory();
		$random = new Random($world->getSeed() ^ ($x * 31 + $z * 17 + $y) ^ $salt);
		$size = $inventory->getSize();
		foreach($this->rollLoot($lootType, $random) as $item){
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

	private function spawnMob(World $world, int $minX, int $minZ, int $x, int $y, int $z, string $mob) : void{
		if(!self::inChunk($minX, $minZ, $x, $z)){
			return; //a neighbouring chunk owns this point
		}
		//two clear cells over a solid floor, so the mob doesn't spawn buried in a wall or over the void
		if($world->getBlockAt($x, $y, $z)->isSolid()
			|| $world->getBlockAt($x, $y + 1, $z)->isSolid()
			|| !$world->getBlockAt($x, $y - 1, $z)->isSolid()){
			return;
		}
		$location = Location::fromObject(new Vector3($x + 0.5, (float) $y, $z + 0.5), $world);
		//explicit instantiation per type (the codebase forbids `new $class()`)
		$entity = match($mob){
			"witch" => new Witch($location),
			"pillager" => new Pillager($location),
			default => null
		};
		if($entity === null){
			return;
		}
		$entity->setPersistent();
		$entity->spawnToAll();
	}

	/**
	 * @return Item[]
	 */
	private function rollLoot(string $type, Random $random) : array{
		return match($type){
			"desert_temple" => DesertTempleLoot::roll($random),
			"jungle_temple" => DesertTempleLoot::roll($random), //jungle treasure mirrors the desert pyramid theme
			"pillager_outpost" => PillagerOutpostLoot::roll($random),
			"igloo" => IglooLoot::roll($random),
			"ruined_portal" => RuinedPortalLoot::roll($random),
			"shipwreck" => ShipwreckLoot::roll($random),
			"buried_treasure" => BuriedTreasureLoot::roll($random),
			"ocean_ruins" => OceanRuinsLoot::roll($random),
			default => []
		};
	}

	private static function inChunk(int $minX, int $minZ, int $x, int $z) : bool{
		return $x >= $minX && $x < $minX + Chunk::EDGE_LENGTH && $z >= $minZ && $z < $minZ + Chunk::EDGE_LENGTH;
	}
}
