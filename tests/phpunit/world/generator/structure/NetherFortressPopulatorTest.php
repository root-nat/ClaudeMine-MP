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

use PHPUnit\Framework\TestCase;
use pocketmine\math\Facing;
use pocketmine\world\generator\carver\ArrayVolume;

class NetherFortressPopulatorTest extends TestCase{

	private function structure() : NetherFortressStructure{
		return new NetherFortressStructure(
			ArrayVolume::AIR, 10, 11, 12, 13, 14,
			[Facing::NORTH => 20, Facing::SOUTH => 21, Facing::EAST => 22, Facing::WEST => 23],
			[Facing::NORTH => 30, Facing::SOUTH => 31, Facing::EAST => 32, Facing::WEST => 33]
		);
	}

	private function populator(int $rarity) : NetherFortressPopulator{
		return new NetherFortressPopulator(worldSeed: 1234, fortress: $this->structure(), rarity: $rarity);
	}

	private function loadedVolume() : ArrayVolume{
		$volume = new ArrayVolume(-64, 320);
		$volume->loadRegion(-4, 4, -4, 4);
		return $volume;
	}

	public function testRarityOnePlacesAndWritesBlocks() : void{
		$volume = $this->loadedVolume();
		self::assertTrue($this->populator(1)->tryPlace($volume, 0, 0));
		self::assertNotEmpty($volume->airCells(), "A placed fortress must carve out air");
	}

	public function testDeterministicForSameSeed() : void{
		$a = $this->loadedVolume();
		$b = $this->loadedVolume();
		$this->populator(1)->tryPlace($a, 0, 0);
		$this->populator(1)->tryPlace($b, 0, 0);
		self::assertSame($a->airCells(), $b->airCells());
	}

	public function testNeverThrowsOrWritesOutsideLoadedChunks() : void{
		$volume = new ArrayVolume(-64, 320);
		$volume->loadChunk(0, 0); //only the centre chunk is loaded
		$this->populator(1)->tryPlace($volume, 0, 0); //must not throw despite fortresses overflowing into unloaded chunks

		//a cell in an unloaded neighbour chunk must remain untouched
		self::assertSame(ArrayVolume::AIR, $volume->getBlockStateId(40, 60, 40));
	}
}
