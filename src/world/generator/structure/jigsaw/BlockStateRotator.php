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

use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\PillarRotation;
use pocketmine\math\Axis;
use pocketmine\math\Facing;

/**
 * Rotates a block STATE id about the Y axis by whole quarter-turns, mirroring the position rotation a {@link
 * StructureTemplate} applies to its cells. A jigsaw piece that is placed rotated must also turn its oriented blocks
 * (stairs, logs, ladders, furnaces, chests, ...) or a rotated house would have doors and roofs facing the wrong way.
 *
 * The state id is decoded to a {@link \pocketmine\block\Block} (a clone, safe to mutate), its orientation property is
 * rotated according to which interface it implements, then it is re-encoded:
 *  - {@link HorizontalFacing} / {@link AnyFacing}: the facing is turned clockwise with {@link Facing::rotateY} once per
 *    step; UP/DOWN facings are invariant under a Y rotation (and would make rotateY throw), so they are left untouched.
 *  - {@link PillarRotation}: a 90/270-degree turn swaps the X and Z axes; Y and 180-degree turns leave the axis as-is.
 * Blocks with no orientation (e.g. planks) and unknown states pass through unchanged.
 */
final class BlockStateRotator{

	private function __construct(){
		//NOOP
	}

	/**
	 * @param int $steps quarter-turns clockwise about Y (any integer; only the low two bits matter)
	 */
	public static function rotateY(int $stateId, int $steps) : int{
		$steps &= 3;
		if($steps === 0){
			return $stateId;
		}
		$block = RuntimeBlockStateRegistry::getInstance()->fromStateId($stateId);

		if($block instanceof HorizontalFacing){
			$block->setFacing(self::turnFacing($block->getFacing(), $steps));
		}elseif($block instanceof AnyFacing){
			$facing = $block->getFacing();
			if(Facing::axis($facing) !== Axis::Y){ //UP/DOWN are unchanged by a Y rotation
				$block->setFacing(self::turnFacing($facing, $steps));
			}
		}elseif($block instanceof PillarRotation){
			$axis = $block->getAxis();
			if(($steps & 1) === 1 && $axis !== Axis::Y){ //an odd turn swaps the horizontal axes
				$block->setAxis($axis === Axis::X ? Axis::Z : Axis::X);
			}
		}

		return $block->getStateId();
	}

	private static function turnFacing(int $facing, int $steps) : int{
		for($i = 0; $i < $steps; ++$i){
			$facing = Facing::rotateY($facing, true);
		}
		return $facing;
	}
}
