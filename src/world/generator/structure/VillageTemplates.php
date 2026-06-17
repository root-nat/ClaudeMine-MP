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

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\world\generator\structure\jigsaw\JigsawConnector;
use pocketmine\world\generator\structure\jigsaw\JigsawPools;
use pocketmine\world\generator\structure\jigsaw\StructureTemplate;
use pocketmine\world\generator\structure\jigsaw\TemplatePool;

/**
 * Hand-baked jigsaw piece set for a small village: a central well plaza (the start piece), straight path segments
 * ("street" pool) that chain outward and branch sideways, and a couple of small houses ("house" pool) that attach to
 * those branches. The materials come from a {@link VillagePalette} so the same geometry yields an oak / sandstone /
 * spruce village per biome; the plaza and well stay universal cobblestone. Pieces are baked from {@link VanillaBlocks}
 * state ids, so the SAME templates are produced on the async generator thread and the main-thread furnisher.
 *
 * IMPORTANT: cell positions, connectors and markers are palette-INDEPENDENT (only the block ids inside cells change), so
 * the furnisher may re-run the assembly with any palette and still recover identical piece/marker positions.
 *
 * The plaza keeps its anchor column (local 0,0 - a flat cobblestone tile with open air above) untouched by the well
 * (offset to the centre), so {@link SurfaceScan::topSolidY} returns a stable ground Y for every chunk pass and the
 * furnisher. Every piece's floor sits at local y=0 and connectors mate at y=0, so the whole village lies on one plane;
 * {@link JigsawStructure} underpins each floor with foundation blocks.
 */
final class VillageTemplates{

	private function __construct(){
		//NOOP
	}

	/**
	 * The start piece: a 7x7 cobblestone plaza with a small well at its centre and a path connector on each side.
	 */
	public static function start(VillagePalette $palette) : StructureTemplate{
		$cobble = VanillaBlocks::COBBLESTONE()->getStateId();
		$water = VanillaBlocks::WATER()->getStateId();

		$blocks = [];
		for($x = 0; $x <= 6; ++$x){
			for($z = 0; $z <= 6; ++$z){
				$blocks[] = [$x, 0, $z, $cobble]; //flat plaza floor (the anchor column 0,0 is one of these tiles)
			}
		}
		//well at the plaza centre (x2..4, z2..4) - kept clear of the anchor column
		for($x = 2; $x <= 4; ++$x){
			for($z = 2; $z <= 4; ++$z){
				$blocks[] = ($x === 3 && $z === 3) ? [3, 1, 3, $water] : [$x, 1, $z, $cobble];
			}
		}
		$blocks[] = [2, 2, 2, $palette->corner];
		$blocks[] = [4, 2, 2, $palette->corner];
		$blocks[] = [2, 2, 4, $palette->corner];
		$blocks[] = [4, 2, 4, $palette->corner];
		for($x = 2; $x <= 4; ++$x){
			for($z = 2; $z <= 4; ++$z){
				$blocks[] = [$x, 3, $z, $palette->floor]; //well canopy
			}
		}

		$connectors = [
			new JigsawConnector(6, 0, 3, Facing::EAST, "street"),
			new JigsawConnector(0, 0, 3, Facing::WEST, "street"),
			new JigsawConnector(3, 0, 6, Facing::SOUTH, "street"),
			new JigsawConnector(3, 0, 0, Facing::NORTH, "street")
		];
		return new StructureTemplate("village_plaza", 7, 4, 7, $blocks, $connectors);
	}

	/**
	 * A straight path segment: chains to more streets at its two ends ("street" pool) and offers a house hookup on each
	 * side ("house" pool).
	 */
	public static function street(VillagePalette $palette) : StructureTemplate{
		$blocks = [];
		for($x = 0; $x <= 4; ++$x){
			for($z = 0; $z <= 2; ++$z){
				$blocks[] = [$x, 0, $z, $palette->path];
			}
		}
		$connectors = [
			new JigsawConnector(0, 0, 1, Facing::WEST, "street"),
			new JigsawConnector(4, 0, 1, Facing::EAST, "street"),
			new JigsawConnector(2, 0, 0, Facing::NORTH, "house"),
			new JigsawConnector(2, 0, 2, Facing::SOUTH, "house")
		];
		return new StructureTemplate("village_street", 5, 1, 3, $blocks, $connectors);
	}

	/**
	 * A 5x5x5 house with a doorway, glass windows, a stair-eaved roof, a loot chest and a villager. Its single door
	 * connector (north wall, "house" pool) is the connector a street side hooks onto. $variant tweaks the décor and
	 * windows so a village reads as more than one repeated building.
	 */
	public static function house(int $variant, VillagePalette $palette) : StructureTemplate{
		$glass = VanillaBlocks::GLASS()->getStateId();
		$chest = VanillaBlocks::CHEST()->getStateId();
		$torch = VanillaBlocks::TORCH()->getStateId();
		$decor = $variant === 0 ? VanillaBlocks::CRAFTING_TABLE()->getStateId() : VanillaBlocks::BOOKSHELF()->getStateId();

		$blocks = [];
		//floor
		for($x = 0; $x <= 4; ++$x){
			for($z = 0; $z <= 4; ++$z){
				$blocks[] = [$x, 0, $z, $palette->floor];
			}
		}
		//walls y=1..3
		for($y = 1; $y <= 3; ++$y){
			for($x = 0; $x <= 4; ++$x){
				for($z = 0; $z <= 4; ++$z){
					if($x !== 0 && $x !== 4 && $z !== 0 && $z !== 4){
						continue; //interior is open air
					}
					if($z === 0 && $x === 2 && ($y === 1 || $y === 2)){
						continue; //doorway opening (north wall)
					}
					$corner = ($x === 0 || $x === 4) && ($z === 0 || $z === 4);
					if($corner){
						$blocks[] = [$x, $y, $z, $palette->corner];
					}elseif($y === 2 && self::isWindow($x, $z, $variant)){
						$blocks[] = [$x, $y, $z, $glass];
					}else{
						$blocks[] = [$x, $y, $z, $palette->wall];
					}
				}
			}
		}
		//roof y=4: stair eaves on the edges (facing outward), solid fill on corners and interior
		for($x = 0; $x <= 4; ++$x){
			for($z = 0; $z <= 4; ++$z){
				$edge = ($x === 0 || $x === 4 || $z === 0 || $z === 4);
				$corner = ($x === 0 || $x === 4) && ($z === 0 || $z === 4);
				if($edge && !$corner){
					$blocks[] = [$x, 4, $z, match(true){
						$z === 0 => $palette->stairNorth,
						$z === 4 => $palette->stairSouth,
						$x === 0 => $palette->stairWest,
						default => $palette->stairEast
					}];
				}else{
					$blocks[] = [$x, 4, $z, $palette->floor];
				}
			}
		}
		//furnishings: a chest and a décor block on the floor, a torch for light
		$blocks[] = [1, 1, 1, $chest];
		$blocks[] = [3, 1, 1, $decor];
		$blocks[] = [2, 1, 2, $torch];

		$connectors = [new JigsawConnector(2, 0, 0, Facing::NORTH, "house")];
		//markers: the chest (co-located with its chest block) and a villager standing on the floor
		$markers = [
			[1, 1, 1, "chest"],
			[3, 1, 3, "villager"]
		];
		return new StructureTemplate("village_house_" . $variant, 5, 5, 5, $blocks, $connectors, $markers);
	}

	public static function pools(VillagePalette $palette) : JigsawPools{
		$pools = new JigsawPools();
		$pools->add(new TemplatePool("street", [self::street($palette)]));
		$pools->add(new TemplatePool("house", [self::house(0, $palette), self::house(1, $palette)]));
		return $pools;
	}

	private static function isWindow(int $x, int $z, int $variant) : bool{
		if($variant === 0){
			return ($x === 0 && $z === 2) || ($x === 4 && $z === 2) || ($x === 2 && $z === 4);
		}
		return ($x === 2 && $z === 4) || ($x === 0 && $z === 1) || ($x === 4 && $z === 3);
	}
}
