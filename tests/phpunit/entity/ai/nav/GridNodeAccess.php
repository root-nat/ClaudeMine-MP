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
 * In-memory {@link NodeAccess} for unit tests. Cells are air by default; solid cells are marked explicitly. A cell is
 * standable when the cell directly below it is solid.
 */
final class GridNodeAccess implements NodeAccess{

	/** @var array<string, bool> */
	private array $solid = [];
	/** @var array<string, bool> */
	private array $hazard = [];

	public function __construct(
		private int $minY = -64,
		private int $maxY = 320
	){}

	public function setSolid(int $x, int $y, int $z, bool $solid = true) : void{
		$this->solid["$x:$y:$z"] = $solid;
	}

	public function setHazard(int $x, int $y, int $z, bool $hazard = true) : void{
		$this->hazard["$x:$y:$z"] = $hazard;
	}

	/**
	 * Fills a solid floor slab over the given inclusive rectangle at a single Y.
	 */
	public function fillFloor(int $minX, int $maxX, int $minZ, int $maxZ, int $y) : void{
		for($x = $minX; $x <= $maxX; ++$x){
			for($z = $minZ; $z <= $maxZ; ++$z){
				$this->setSolid($x, $y, $z);
			}
		}
	}

	/**
	 * Fills a solid wall column (full height range) at a single x,z.
	 */
	public function fillWall(int $x, int $z, int $minY, int $maxY) : void{
		for($y = $minY; $y <= $maxY; ++$y){
			$this->setSolid($x, $y, $z);
		}
	}

	private function isSolid(int $x, int $y, int $z) : bool{
		return $this->solid["$x:$y:$z"] ?? false;
	}

	public function isPassable(int $x, int $y, int $z) : bool{
		return !$this->isSolid($x, $y, $z);
	}

	public function isStandable(int $x, int $y, int $z) : bool{
		return $this->isSolid($x, $y - 1, $z);
	}

	public function isHazard(int $x, int $y, int $z) : bool{
		return $this->hazard["$x:$y:$z"] ?? false;
	}

	public function getMinY() : int{
		return $this->minY;
	}

	public function getMaxY() : int{
		return $this->maxY;
	}
}
