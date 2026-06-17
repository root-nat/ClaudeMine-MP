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
 * A buried fossil: a bone-block spine with arching ribs, threaded with the odd coal-ore block, hidden a few blocks under
 * the desert/swamp surface. No loot, no mobs - a source of bone blocks and coal.
 *
 * Built entirely BELOW the anchor's ground level, so it never places anything in the anchor column above groundY: the
 * surface scan there keeps returning the same groundY (the untouched natural surface) from every chunk and from the
 * furnisher. Geometry is fully fixed (no Random); the coal speckle is a fixed positional rule.
 */
final class FossilStructure extends Structure{

	public const MAX_RADIUS = 5;
	public const SALT = 0x5a1702;
	public const RARITY = 40;

	private const TOP = -2; //the highest bone sits 2 blocks below the surface (buried)

	private int $originX = 0;
	private int $originY = 0;
	private int $originZ = 0;

	public function __construct(
		private int $boneStateId,
		private int $coalOreStateId
	){}

	public function canPlace(GenerationVolume $volume, int $x, int $y, int $z) : bool{
		return ($y - 9) > $volume->getMinY();
	}

	public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		$this->originX = $x;
		$this->originY = $y;
		$this->originZ = $z;

		$spineY = self::TOP - 4; //the spine runs along the bottom, ribs arch up toward TOP
		//spine: a horizontal row of bone along X
		for($dx = -4; $dx <= 4; ++$dx){
			$this->setBone($volume, $dx, $spineY, 0);
		}
		//ribs: symmetric arches rising from the spine and curving outward along Z
		foreach([-3, -1, 1, 3] as $dx){
			$this->setBone($volume, $dx, $spineY + 1, 0);
			$this->setBone($volume, $dx, $spineY + 2, -1);
			$this->setBone($volume, $dx, $spineY + 2, 1);
			$this->setBone($volume, $dx, $spineY + 3, -2);
			$this->setBone($volume, $dx, $spineY + 3, 2);
		}
	}

	private function setBone(GenerationVolume $volume, int $dx, int $dy, int $dz) : void{
		//thread coal ore through the bone by a fixed positional rule (no Random, so it stays identical across chunks)
		$this->set($volume, $dx, $dy, $dz, (($dx * 3 + $dy * 5 + $dz) & 3) === 0 ? $this->coalOreStateId : $this->boneStateId);
	}

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
