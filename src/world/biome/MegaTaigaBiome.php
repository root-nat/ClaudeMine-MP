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

use pocketmine\block\VanillaBlocks;
use pocketmine\world\generator\object\TreeType;
use pocketmine\world\generator\populator\Tree;

/**
 * A mega (giant) taiga: dense spruce forest over a podzol floor.
 */
class MegaTaigaBiome extends Biome{

	public function __construct(){
		$this->setGroundCover([
			VanillaBlocks::PODZOL(),
			VanillaBlocks::DIRT(),
			VanillaBlocks::DIRT(),
			VanillaBlocks::DIRT(),
			VanillaBlocks::DIRT()
		]);

		$trees = new Tree(TreeType::SPRUCE);
		$trees->setBaseAmount(8);
		$trees->setRandomAmount(3);
		$this->addPopulator($trees);

		$this->setElevation(63, 81);

		$this->temperature = 0.3;
		$this->rainfall = 0.8;
	}

	public function getName() : string{
		return "Mega Taiga";
	}
}
