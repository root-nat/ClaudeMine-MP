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

class NetherBastionStructureTest extends TestCase{
	private const BLACKSTONE = 10;
	private const BRICKS = 11;
	private const GILDED = 12;
	private const GOLD = 13;
	private const MAGMA = 14;
	private const CHEST = 15;

	private const ANCHOR_Y = 40;
	private const R = NetherBastionStructure::MAX_RADIUS;

	private function structure() : NetherBastionStructure{
		return new NetherBastionStructure(ArrayVolume::AIR, self::BLACKSTONE, self::BRICKS, self::GILDED, self::GOLD, self::MAGMA, self::CHEST);
	}

	private function loadedVolume() : ArrayVolume{
		$volume = new ArrayVolume(-64, 320);
		$volume->loadRegion(-2, 2, -2, 2);
		return $volume;
	}

	private function tally(ArrayVolume $volume, int $stateId) : int{
		$found = 0;
		for($x = -self::R; $x <= self::R; ++$x){
			for($z = -self::R; $z <= self::R; ++$z){
				for($y = self::ANCHOR_Y - 6; $y <= self::ANCHOR_Y + 6; ++$y){
					if($volume->getBlockStateId($x, $y, $z) === $stateId){
						++$found;
					}
				}
			}
		}
		return $found;
	}

	public function testPlacesCourtyardWithExactlyOneChest() : void{
		$volume = $this->loadedVolume();
		$this->structure()->place($volume, 0, self::ANCHOR_Y, 0, new Random(11));

		self::assertGreaterThan(50, $this->tally($volume, self::BRICKS) + $this->tally($volume, self::BLACKSTONE), "Bastion must lay a substantial blackstone footprint");
		self::assertSame(1, $this->tally($volume, self::CHEST), "Exactly one treasure chest");
	}

	public function testChestSitsAtFixedOffset() : void{
		$volume = $this->loadedVolume();
		$this->structure()->place($volume, 0, self::ANCHOR_Y, 0, new Random(3));
		[$cdx, $cdy, $cdz] = NetherBastionStructure::CHEST_OFFSET;
		//the furnisher relies on this exact offset to find and fill the chest
		self::assertSame(self::CHEST, $volume->getBlockStateId($cdx, self::ANCHOR_Y + $cdy, $cdz));
	}

	public function testTreasureHasGold() : void{
		$volume = $this->loadedVolume();
		$this->structure()->place($volume, 0, self::ANCHOR_Y, 0, new Random(8));
		self::assertGreaterThan(0, $this->tally($volume, self::GOLD), "The treasure must include gold blocks");
	}

	public function testPiglinSpotsAreOpenFloor() : void{
		$volume = $this->loadedVolume();
		$this->structure()->place($volume, 0, self::ANCHOR_Y, 0, new Random(21));
		foreach(NetherBastionStructure::PIGLIN_OFFSETS as [$dx, $dy, $dz]){
			//feet cell clear, floor solid below - so the furnisher can stand a piglin here
			self::assertSame(ArrayVolume::AIR, $volume->getBlockStateId($dx, self::ANCHOR_Y + $dy, $dz), "Piglin spot must be open air");
			self::assertNotSame(ArrayVolume::AIR, $volume->getBlockStateId($dx, self::ANCHOR_Y + $dy - 1, $dz), "Piglin spot must have a floor");
		}
	}

	public function testDeterministicForSameSeed() : void{
		$a = $this->loadedVolume();
		$b = $this->loadedVolume();
		$this->structure()->place($a, 0, self::ANCHOR_Y, 0, new Random(2024));
		$this->structure()->place($b, 0, self::ANCHOR_Y, 0, new Random(2024));
		self::assertSame($a->airCells(), $b->airCells());
	}

	public function testNeverWritesOutsideBounds() : void{
		$volume = new ArrayVolume(-64, 320);
		$volume->loadChunk(0, 0); //only the centre chunk is loaded
		$this->structure()->place($volume, 8, self::ANCHOR_Y, 8, new Random(5));

		//the chest at (8,*,8)+offset(5,5) = (13,13) lands inside the loaded chunk
		self::assertSame(self::CHEST, $volume->getBlockStateId(13, self::ANCHOR_Y + 1, 13));
		//nothing leaked into an unloaded neighbour chunk
		self::assertSame(ArrayVolume::AIR, $volume->getBlockStateId(40, self::ANCHOR_Y, 40));
	}
}
