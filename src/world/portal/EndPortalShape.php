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

namespace pocketmine\world\portal;

use pocketmine\block\EndPortalFrame;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class EndPortalShape{

	private function __construct(){
	}

	public static function tryComplete(World $world, Vector3 $framePos) : bool{
		$frame = $world->getBlock($framePos);
		if(!$frame instanceof EndPortalFrame){
			return false;
		}

		$facing = $frame->getFacing();
		$centerCandidate = $framePos->getSide($facing, 2);
		$candidates = [
			$centerCandidate,
			$centerCandidate->getSide(Facing::rotateY($facing, true)),
			$centerCandidate->getSide(Facing::rotateY($facing, false))
		];

		foreach($candidates as $center){
			if(self::isComplete($world, $center)){
				self::fill($world, $center);
				return true;
			}
		}
		return false;
	}

	public static function isComplete(World $world, Vector3 $center) : bool{
		$x = $center->getFloorX();
		$y = $center->getFloorY();
		$z = $center->getFloorZ();

		for($i = -1; $i <= 1; ++$i){
			if(
				!self::isActiveFrame($world, $x + $i, $y, $z - 2, Facing::SOUTH) ||
				!self::isActiveFrame($world, $x + $i, $y, $z + 2, Facing::NORTH) ||
				!self::isActiveFrame($world, $x - 2, $y, $z + $i, Facing::EAST) ||
				!self::isActiveFrame($world, $x + 2, $y, $z + $i, Facing::WEST)
			){
				return false;
			}
		}
		return true;
	}

	private static function isActiveFrame(World $world, int $x, int $y, int $z, int $expectedFacing) : bool{
		$block = $world->getBlockAt($x, $y, $z);
		return $block instanceof EndPortalFrame && $block->hasEye() && $block->getFacing() === $expectedFacing;
	}

	private static function fill(World $world, Vector3 $center) : void{
		$x = $center->getFloorX();
		$y = $center->getFloorY();
		$z = $center->getFloorZ();
		$portal = VanillaBlocks::END_PORTAL();

		for($dx = -1; $dx <= 1; ++$dx){
			for($dz = -1; $dz <= 1; ++$dz){
				$world->setBlockAt($x + $dx, $y, $z + $dz, $portal, false);
			}
		}
	}
}
