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

namespace pocketmine\world\generator\carver;

use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\generator\populator\Populator;

/**
 * Bridges {@link Carver}s into the generator's Populator pipeline. Registered in Normal's generationPopulators BEFORE
 * GroundCover so the surface layer is re-applied over any cave mouths the carvers expose.
 */
final class CarverPopulator implements Populator{

	/**
	 * @param Carver[] $carvers
	 */
	public function __construct(
		private int $worldSeed,
		private array $carvers
	){}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$volume = new ChunkManagerVolume($world);
		foreach($this->carvers as $carver){
			$carver->carve($volume, $chunkX, $chunkZ, $this->worldSeed);
		}
	}
}
