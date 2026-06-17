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

namespace pocketmine\world\generator\structure\jigsaw;

use pocketmine\math\Facing;

/**
 * A jigsaw connection point on a {@link StructureTemplate}: a local cell with an outward horizontal facing and the name
 * of the template pool whose pieces may attach here. The {@link JigsawAssembler} mates a connector facing one way with
 * a connector on another piece facing the opposite way.
 */
final class JigsawConnector{

	public function __construct(
		public readonly int $x,
		public readonly int $y,
		public readonly int $z,
		public readonly int $facing,
		public readonly string $pool
	){}

	/**
	 * Returns this connector rotated by $rotation quarter-turns clockwise about Y, within a template footprint of the
	 * given pre-rotation size.
	 */
	public function rotated(int $rotation, int $sizeX, int $sizeZ) : self{
		$rotation &= 3;
		$x = $this->x;
		$z = $this->z;
		for($i = 0; $i < $rotation; ++$i){
			//90 deg clockwise within the current footprint (sizeX shrinks/grows as it swaps each step)
			[$x, $z, $sizeX, $sizeZ] = [$sizeZ - 1 - $z, $x, $sizeZ, $sizeX];
		}
		$facing = $this->facing;
		for($i = 0; $i < $rotation; ++$i){
			$facing = Facing::rotateY($facing, true);
		}
		return new self($x, $this->y, $z, $facing, $this->pool);
	}
}
