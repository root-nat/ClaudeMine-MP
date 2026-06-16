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
use pocketmine\utils\Random;
use pocketmine\world\generator\carver\ArrayVolume;

class DungeonStructureTest extends TestCase{
	private const COBBLE = 10;
	private const MOSSY = 11;
	private const SPAWNER = 12;
	private const CHEST = 13;

	private function structure() : DungeonStructure{
		return new DungeonStructure(ArrayVolume::AIR, self::COBBLE, self::MOSSY, self::SPAWNER, self::CHEST);
	}

	private function volumeWithCavity() : ArrayVolume{
		$volume = new ArrayVolume(-64, 320);
		$volume->loadRegion(-1, 1, -1, 1);
		//carve a small air cavity around the anchor so canPlace() is satisfied
		for($dx = -1; $dx <= 1; ++$dx){
			for($dz = -1; $dz <= 1; ++$dz){
				for($dy = 0; $dy < 3; ++$dy){
					$volume->setBlockStateId($dx, 20 + $dy, $dz, ArrayVolume::AIR);
				}
			}
		}
		return $volume;
	}

	public function testCanPlaceRequiresFloor() : void{
		$volume = new ArrayVolume(-64, 320);
		$volume->loadRegion(-1, 1, -1, 1);
		//hollow out the floor under the anchor -> cannot place
		$volume->setBlockStateId(0, 19, 0, ArrayVolume::AIR);
		$volume->setBlockStateId(0, 20, 0, ArrayVolume::AIR);
		self::assertFalse($this->structure()->canPlace($volume, 0, 20, 0));
	}

	public function testCanPlaceInCavity() : void{
		self::assertTrue($this->structure()->canPlace($this->volumeWithCavity(), 0, 20, 0));
	}

	public function testPlacesExactlyOneSpawnerAtCentre() : void{
		$volume = $this->volumeWithCavity();
		$this->structure()->place($volume, 0, 20, 0, new Random(1));
		self::assertSame(self::SPAWNER, $volume->getBlockStateId(0, 20, 0));

		$spawners = 0;
		for($x = -5; $x <= 5; ++$x){
			for($z = -5; $z <= 5; ++$z){
				for($y = 18; $y <= 25; ++$y){
					if($volume->getBlockStateId($x, $y, $z) === self::SPAWNER){
						++$spawners;
					}
				}
			}
		}
		self::assertSame(1, $spawners);
	}

	public function testInteriorIsAir() : void{
		$volume = $this->volumeWithCavity();
		$this->structure()->place($volume, 0, 20, 0, new Random(7));
		//cells immediately beside the spawner at head height should be hollow
		self::assertSame(ArrayVolume::AIR, $volume->getBlockStateId(1, 21, 0));
		self::assertSame(ArrayVolume::AIR, $volume->getBlockStateId(0, 21, 1));
	}

	public function testWallsAreCobblestoneFamily() : void{
		$volume = $this->volumeWithCavity();
		$this->structure()->place($volume, 0, 20, 0, new Random(3));
		//a far corner of the shell must be a cobble-family block
		$wall = $volume->getBlockStateId(3, 20, 3);
		self::assertContains($wall, [self::COBBLE, self::MOSSY], "Walls must be cobblestone or mossy cobblestone");
	}

	public function testChestCountInRange() : void{
		$volume = $this->volumeWithCavity();
		$this->structure()->place($volume, 0, 20, 0, new Random(42));
		$chests = 0;
		for($x = -5; $x <= 5; ++$x){
			for($z = -5; $z <= 5; ++$z){
				for($y = 18; $y <= 25; ++$y){
					if($volume->getBlockStateId($x, $y, $z) === self::CHEST){
						++$chests;
					}
				}
			}
		}
		self::assertGreaterThanOrEqual(1, $chests);
		self::assertLessThanOrEqual(2, $chests);
	}

	public function testDeterministicForSameSeed() : void{
		$a = $this->volumeWithCavity();
		$b = $this->volumeWithCavity();
		$this->structure()->place($a, 0, 20, 0, new Random(2024));
		$this->structure()->place($b, 0, 20, 0, new Random(2024));
		self::assertSame($a->airCells(), $b->airCells());
	}

	public function testNeverWritesOutsideBounds() : void{
		$volume = new ArrayVolume(-64, 320);
		$volume->loadChunk(0, 0); //only the centre chunk is loaded
		for($dy = 0; $dy < 3; ++$dy){
			$volume->setBlockStateId(0, 20 + $dy, 0, ArrayVolume::AIR);
		}
		//anchor near the chunk edge so the room would overflow into unloaded chunks; place() must clip silently
		$this->structure()->place($volume, 15, 20, 15, new Random(5));
		//no exception thrown and nothing set in the unloaded neighbour
		self::assertSame(ArrayVolume::AIR, $volume->getBlockStateId(16, 20, 16));
	}
}
