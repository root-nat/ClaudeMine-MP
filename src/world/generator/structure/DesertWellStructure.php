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

use pocketmine\utils\Random;
use pocketmine\world\generator\carver\GenerationVolume;
use function abs;

/**
 * A desert well: a small sandstone basin holding a single water source, ringed by a sandstone wall and capped with
 * sandstone slabs at the corners. No loot, no mobs - a decorative desert landmark and the simplest surface structure,
 * proving the {@link SurfaceStructurePopulator} surface-anchor + biome-gate + determinism pipeline.
 *
 * Anchored centred on (ax, groundY, az): the centre column is sandstone at groundY with water above, so {@link
 * SurfaceScan::topSolidY} at the anchor column always returns groundY whether or not the well is built - the contract
 * that keeps placement identical across chunk seams and agreeing with the furnisher. The water is fully walled so it
 * cannot flow at runtime. Geometry is fully fixed (no Random), so it is trivially identical across chunks.
 */
final class DesertWellStructure extends Structure{

	public const MAX_RADIUS = 2;

	//placement params shared by the populator (Normal.php) and the /locate command, so locate reports where wells generate
	public const SALT = 0x5a1701;
	public const RARITY = 24;

	public function __construct(
		private int $sandstoneStateId,
		private int $sandstoneSlabTopStateId,
		private int $waterStateId
	){}

	public function canPlace(GenerationVolume $volume, int $x, int $y, int $z) : bool{
		return $volume->isInBounds($x, $y, $z) && ($y + 3) < $volume->getMaxY();
	}

	public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		$this->originX = $x;
		$this->originY = $y;
		$this->originZ = $z;

		//base: 3x3 sandstone floor at ground level (overwrites the sand top); the centre stays sandstone so the surface
		//scan at the anchor column keeps returning groundY
		for($dx = -1; $dx <= 1; ++$dx){
			for($dz = -1; $dz <= 1; ++$dz){
				$this->set($volume, $dx, 0, $dz, $this->sandstoneStateId);
			}
		}
		//walled basin one block up: sandstone ring around a central water source (walls keep the water from flowing)
		for($dx = -1; $dx <= 1; ++$dx){
			for($dz = -1; $dz <= 1; ++$dz){
				$this->set($volume, $dx, 1, $dz, $dx === 0 && $dz === 0 ? $this->waterStateId : $this->sandstoneStateId);
			}
		}
		//slab caps on the four corner posts
		foreach([[-1, -1], [-1, 1], [1, -1], [1, 1]] as [$dx, $dz]){
			$this->set($volume, $dx, 2, $dz, $this->sandstoneSlabTopStateId);
		}
	}

	private int $originX = 0;
	private int $originY = 0;
	private int $originZ = 0;

	private function set(GenerationVolume $volume, int $dx, int $dy, int $dz, int $stateId) : void{
		if(abs($dx) > self::MAX_RADIUS || abs($dz) > self::MAX_RADIUS){
			return;
		}
		$x = $this->originX + $dx;
		$y = $this->originY + $dy;
		$z = $this->originZ + $dz;
		if($volume->isInBounds($x, $y, $z)){
			$volume->setBlockStateId($x, $y, $z, $stateId);
		}
	}
}
