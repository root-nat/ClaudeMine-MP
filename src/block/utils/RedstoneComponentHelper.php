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

namespace pocketmine\block\utils;

use pocketmine\block\Block;
use pocketmine\block\Button;
use pocketmine\block\DaylightSensor;
use pocketmine\block\Lever;
use pocketmine\block\PressurePlate;
use pocketmine\block\RedstoneComparator;
use pocketmine\block\RedstoneRepeater;
use pocketmine\block\RedstoneTorch;
use pocketmine\block\RedstoneWire;
use pocketmine\math\Facing;

final class RedstoneComponentHelper{

	private function __construct(){
	}

	public static function isComponent(Block $block) : bool{
		return $block instanceof RedstoneWire
			|| $block instanceof RedstoneTorch
			|| $block instanceof RedstoneRepeater
			|| $block instanceof RedstoneComparator
			|| $block instanceof Lever
			|| $block instanceof Button
			|| $block instanceof PressurePlate
			|| $block instanceof DaylightSensor;
	}

	/**
	 * Returns whether any block adjacent to the given block is a redstone component.
	 * Used by blocks like doors which need to distinguish circuit-driven updates from unrelated neighbour changes,
	 * since their Bedrock block states cannot store a "powered" flag.
	 */
	public static function hasAdjacentComponent(Block $block) : bool{
		foreach(Facing::ALL as $face){
			if(self::isComponent($block->getSide($face))){
				return true;
			}
		}
		return false;
	}
}
