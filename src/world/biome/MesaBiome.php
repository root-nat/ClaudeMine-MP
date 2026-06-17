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

namespace pocketmine\world\biome;

use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;

/**
 * Badlands (mesa): a barren, very hot and dry biome of red sand over banded terracotta, with no trees.
 */
class MesaBiome extends Biome{

	public function __construct(){
		$this->setGroundCover([
			VanillaBlocks::RED_SAND(),
			VanillaBlocks::RED_SAND(),
			VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::ORANGE),
			VanillaBlocks::HARDENED_CLAY(),
			VanillaBlocks::HARDENED_CLAY()
		]);

		$this->setElevation(63, 79);

		$this->temperature = 2.0;
		$this->rainfall = 0.0;
	}

	public function getName() : string{
		return "Badlands";
	}
}
