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
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;

/**
 * The block-state palette a {@link VillageTemplates} village is built from, so the same piece geometry yields a
 * biome-appropriate village: oak in plains/savanna/taiga, sandstone in the desert, spruce in the snowy taiga. Only the
 * material STATE IDS differ between palettes - cell positions, connectors and markers are identical - so the {@link
 * VillageFurnisher} can re-derive marker positions from any palette regardless of which one the generator used.
 *
 * The plaza/well stay universal (cobblestone) in every palette; the houses, their roofs and the paths are themed.
 */
final class VillagePalette{

	private function __construct(
		public readonly int $floor,
		public readonly int $floorTypeId,
		public readonly int $wall,
		public readonly int $corner,
		public readonly int $path,
		public readonly int $foundation,
		public readonly int $stairNorth,
		public readonly int $stairSouth,
		public readonly int $stairEast,
		public readonly int $stairWest
	){}

	public static function plains() : self{
		return new self(
			VanillaBlocks::OAK_PLANKS()->getStateId(),
			BlockTypeIds::OAK_PLANKS,
			VanillaBlocks::OAK_PLANKS()->getStateId(),
			VanillaBlocks::OAK_LOG()->getStateId(),
			VanillaBlocks::GRASS_PATH()->getStateId(),
			VanillaBlocks::COBBLESTONE()->getStateId(),
			VanillaBlocks::OAK_STAIRS()->setFacing(Facing::NORTH)->getStateId(),
			VanillaBlocks::OAK_STAIRS()->setFacing(Facing::SOUTH)->getStateId(),
			VanillaBlocks::OAK_STAIRS()->setFacing(Facing::EAST)->getStateId(),
			VanillaBlocks::OAK_STAIRS()->setFacing(Facing::WEST)->getStateId()
		);
	}

	public static function desert() : self{
		return new self(
			VanillaBlocks::SMOOTH_SANDSTONE()->getStateId(),
			BlockTypeIds::SMOOTH_SANDSTONE,
			VanillaBlocks::SANDSTONE()->getStateId(),
			VanillaBlocks::CUT_SANDSTONE()->getStateId(),
			VanillaBlocks::SMOOTH_SANDSTONE()->getStateId(),
			VanillaBlocks::SANDSTONE()->getStateId(),
			VanillaBlocks::SANDSTONE_STAIRS()->setFacing(Facing::NORTH)->getStateId(),
			VanillaBlocks::SANDSTONE_STAIRS()->setFacing(Facing::SOUTH)->getStateId(),
			VanillaBlocks::SANDSTONE_STAIRS()->setFacing(Facing::EAST)->getStateId(),
			VanillaBlocks::SANDSTONE_STAIRS()->setFacing(Facing::WEST)->getStateId()
		);
	}

	public static function snowy() : self{
		return new self(
			VanillaBlocks::SPRUCE_PLANKS()->getStateId(),
			BlockTypeIds::SPRUCE_PLANKS,
			VanillaBlocks::SPRUCE_PLANKS()->getStateId(),
			VanillaBlocks::SPRUCE_LOG()->getStateId(),
			VanillaBlocks::GRASS_PATH()->getStateId(),
			VanillaBlocks::COBBLESTONE()->getStateId(),
			VanillaBlocks::SPRUCE_STAIRS()->setFacing(Facing::NORTH)->getStateId(),
			VanillaBlocks::SPRUCE_STAIRS()->setFacing(Facing::SOUTH)->getStateId(),
			VanillaBlocks::SPRUCE_STAIRS()->setFacing(Facing::EAST)->getStateId(),
			VanillaBlocks::SPRUCE_STAIRS()->setFacing(Facing::WEST)->getStateId()
		);
	}

	/**
	 * Whether the given block type id is one a village house floors with - the {@link VillageFurnisher}'s structural gate
	 * for spawning a villager (the villager-spawn analogue of "the chest block must actually be a CHEST"). Must list every
	 * palette's floor material.
	 */
	public static function isHouseFloor(int $typeId) : bool{
		return $typeId === BlockTypeIds::OAK_PLANKS
			|| $typeId === BlockTypeIds::SMOOTH_SANDSTONE
			|| $typeId === BlockTypeIds::SPRUCE_PLANKS;
	}
}
