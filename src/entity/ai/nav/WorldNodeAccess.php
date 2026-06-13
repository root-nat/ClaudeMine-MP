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

namespace pocketmine\entity\ai\nav;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\Liquid;
use pocketmine\math\Facing;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use function count;

/**
 * {@link NodeAccess} backed by a live {@link World}. This is one of only two places (the other being sensors) where the
 * pure AI touches the real world, keeping all navigation decisions deterministically testable elsewhere. Unloaded
 * chunks are treated as impassable so pathfinding never crashes near chunk borders.
 */
final class WorldNodeAccess implements NodeAccess{

	public function __construct(
		private World $world
	){}

	private function isLoaded(int $x, int $z) : bool{
		return $this->world->isChunkLoaded($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE);
	}

	public function isPassable(int $x, int $y, int $z) : bool{
		if(!$this->world->isInWorld($x, $y, $z) || !$this->isLoaded($x, $z)){
			return false;
		}
		$block = $this->world->getBlockAt($x, $y, $z);
		return count($block->getCollisionBoxes()) === 0;
	}

	public function isStandable(int $x, int $y, int $z) : bool{
		$below = $y - 1;
		if(!$this->world->isInWorld($x, $below, $z) || !$this->isLoaded($x, $z)){
			return false;
		}
		$floor = $this->world->getBlockAt($x, $below, $z);
		if($floor instanceof Liquid){
			return false;
		}
		return $floor->getSupportType(Facing::UP)->hasCenterSupport();
	}

	public function isHazard(int $x, int $y, int $z) : bool{
		if(!$this->world->isInWorld($x, $y, $z) || !$this->isLoaded($x, $z)){
			return false;
		}
		$typeId = $this->world->getBlockAt($x, $y, $z)->getTypeId();
		return match($typeId){
			BlockTypeIds::LAVA,
			BlockTypeIds::FIRE,
			BlockTypeIds::SOUL_FIRE,
			BlockTypeIds::CACTUS,
			BlockTypeIds::MAGMA => true,
			default => false
		};
	}

	public function getMinY() : int{
		return $this->world->getMinY();
	}

	public function getMaxY() : int{
		return $this->world->getMaxY();
	}
}
