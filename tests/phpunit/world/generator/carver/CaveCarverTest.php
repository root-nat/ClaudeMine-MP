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

use PHPUnit\Framework\TestCase;
use function count;

class CaveCarverTest extends TestCase{

	private function carvableOnly() : \Closure{
		return fn(int $stateId) => $stateId === ArrayVolume::STONE;
	}

	private function makeVolume() : ArrayVolume{
		$volume = new ArrayVolume(-64, 320);
		$volume->loadRegion(-1, 1, -1, 1); //3x3 chunk region around origin
		return $volume;
	}

	private function carver(int $maxCarveY = 50) : CaveCarver{
		return new CaveCarver(ArrayVolume::AIR, $this->carvableOnly(), 1, $maxCarveY, -59);
	}

	public function testCarvesSomeAir() : void{
		$volume = $this->makeVolume();
		$this->carver()->carve($volume, 0, 0, 4242);
		self::assertGreaterThan(0, count($volume->airCells()), "Carver should hollow out some stone");
	}

	public function testDeterministicForSameSeed() : void{
		$a = $this->makeVolume();
		$b = $this->makeVolume();
		$this->carver()->carve($a, 0, 0, 9001);
		$this->carver()->carve($b, 0, 0, 9001);
		self::assertSame($a->airCells(), $b->airCells());
	}

	public function testDifferentSeedDiffersOrAtLeastValid() : void{
		$a = $this->makeVolume();
		$b = $this->makeVolume();
		$this->carver()->carve($a, 0, 0, 1);
		$this->carver()->carve($b, 0, 0, 2);
		//overwhelmingly likely to differ; at minimum both are valid carves
		self::assertNotSame($a->airCells(), $b->airCells());
	}

	public function testBedrockFloorPreserved() : void{
		$volume = $this->makeVolume();
		//lay bedrock across the bottom row of the centre chunk
		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				$volume->setBlockStateId($x, -64, $z, ArrayVolume::BEDROCK);
			}
		}
		$this->carver()->carve($volume, 0, 0, 55);
		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				self::assertSame(ArrayVolume::BEDROCK, $volume->getBlockStateId($x, -64, $z), "Bedrock at $x,-64,$z must survive");
			}
		}
	}

	public function testNeverCarvesAboveMaxCarveY() : void{
		$volume = $this->makeVolume();
		$this->carver(40)->carve($volume, 0, 0, 7777);
		foreach($volume->airCells() as $cell){
			[, $y, ] = array_map('intval', explode(":", $cell));
			self::assertLessThanOrEqual(40, $y, "No cell above maxCarveY should be carved");
		}
	}

	public function testNeverCarvesBelowFloorMargin() : void{
		$volume = $this->makeVolume();
		$this->carver()->carve($volume, 0, 0, 31337);
		foreach($volume->airCells() as $cell){
			[, $y, ] = array_map('intval', explode(":", $cell));
			self::assertGreaterThanOrEqual(-63, $y, "Floor margin must be preserved (minY + 1)");
		}
	}

	public function testDoesNotCarveNonStone() : void{
		$volume = $this->makeVolume();
		//fill a horizontal slab of bedrock mid-range; it must never become air
		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				$volume->setBlockStateId($x, 20, $z, ArrayVolume::BEDROCK);
			}
		}
		$this->carver()->carve($volume, 0, 0, 64);
		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				self::assertNotSame(ArrayVolume::AIR, $volume->getBlockStateId($x, 20, $z));
			}
		}
	}
}
