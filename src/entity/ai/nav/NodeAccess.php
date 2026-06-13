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

/**
 * Abstraction over the world used by the pathfinder. This is the single seam between the pure navigation logic and the
 * live World, allowing the A* search, node evaluator and navigator to be unit-tested against an in-memory grid.
 */
interface NodeAccess{

	/**
	 * Returns whether the block cell at the given coordinates has no collision (a mob's body can occupy it).
	 */
	public function isPassable(int $x, int $y, int $z) : bool;

	/**
	 * Returns whether the block directly below (x, y, z) provides a surface a mob can stand on.
	 */
	public function isStandable(int $x, int $y, int $z) : bool;

	/**
	 * Returns whether the block cell would be hazardous to step into (lava, fire, cactus...). Hazards are avoided unless
	 * unavoidable. Implementations with no hazard concept may always return false.
	 */
	public function isHazard(int $x, int $y, int $z) : bool;

	public function getMinY() : int;

	public function getMaxY() : int;
}
