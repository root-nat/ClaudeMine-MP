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
use function abs;
use function count;
use function explode;
use function max;
use function min;

class RavineCarverTest extends TestCase{

	private function carvableOnly() : \Closure{
		return fn(int $stateId) => $stateId === ArrayVolume::STONE;
	}

	private function makeVolume() : ArrayVolume{
		$volume = new ArrayVolume(-64, 320);
		$volume->loadRegion(-1, 1, -1, 1);
		return $volume;
	}

	/**
	 * @return array{int, int, int, int, int, int} minX,maxX,minY,maxY,minZ,maxZ of the carved bounding box
	 */
	private function boundingBox(ArrayVolume $volume) : array{
		$minX = $minY = $minZ = PHP_INT_MAX;
		$maxX = $maxY = $maxZ = PHP_INT_MIN;
		foreach($volume->airCells() as $cell){
			[$x, $y, $z] = array_map('intval', explode(":", $cell));
			$minX = min($minX, $x); $maxX = max($maxX, $x);
			$minY = min($minY, $y); $maxY = max($maxY, $y);
			$minZ = min($minZ, $z); $maxZ = max($maxZ, $z);
		}
		return [$minX, $maxX, $minY, $maxY, $minZ, $maxZ];
	}

	public function testRarityGateSuppressesMostChunks() : void{
		//rarity 60: a single chunk almost never carves a ravine
		$carved = 0;
		for($seed = 0; $seed < 20; ++$seed){
			$volume = $this->makeVolume();
			(new RavineCarver(ArrayVolume::AIR, $this->carvableOnly(), 60, 1, 50, -50))->carve($volume, 0, 0, $seed);
			if(count($volume->airCells()) > 0){
				++$carved;
			}
		}
		self::assertLessThan(10, $carved, "Ravines should be rare with rarity 60");
	}

	public function testAlwaysCarvesWhenRarityOne() : void{
		$volume = $this->makeVolume();
		(new RavineCarver(ArrayVolume::AIR, $this->carvableOnly(), 1, 1, 50, -50))->carve($volume, 0, 0, 123);
		self::assertGreaterThan(0, count($volume->airCells()));
	}

	public function testRavineIsTall() : void{
		$volume = $this->makeVolume();
		(new RavineCarver(ArrayVolume::AIR, $this->carvableOnly(), 1, 1, 50, -50))->carve($volume, 0, 0, 555);
		[, , $minY, $maxY, , ] = $this->boundingBox($volume);
		self::assertGreaterThanOrEqual(12, $maxY - $minY, "A ravine should have a tall vertical extent");
	}

	public function testRavineCrossSectionTapersTowardEdges() : void{
		$volume = $this->makeVolume();
		(new RavineCarver(ArrayVolume::AIR, $this->carvableOnly(), 1, 1, 50, -50))->carve($volume, 0, 0, 555);
		[, , $minY, $maxY, , ] = $this->boundingBox($volume);
		$centerY = (int) (($minY + $maxY) / 2);

		$countAt = static function(ArrayVolume $v, int $targetY) : int{
			$n = 0;
			foreach($v->airCells() as $cell){
				[, $y, ] = array_map('intval', explode(":", $cell));
				if($y === $targetY){
					++$n;
				}
			}
			return $n;
		};

		//the lens-shaped slit is wider (more air) at its vertical centre than at its top edge
		self::assertGreaterThan($countAt($volume, $maxY), $countAt($volume, $centerY));
	}

	public function testDeterministic() : void{
		$a = $this->makeVolume();
		$b = $this->makeVolume();
		(new RavineCarver(ArrayVolume::AIR, $this->carvableOnly(), 1, 1, 50, -50))->carve($a, 0, 0, 8888);
		(new RavineCarver(ArrayVolume::AIR, $this->carvableOnly(), 1, 1, 50, -50))->carve($b, 0, 0, 8888);
		self::assertSame($a->airCells(), $b->airCells());
	}

	public function testBedrockPreserved() : void{
		$volume = $this->makeVolume();
		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				$volume->setBlockStateId($x, -64, $z, ArrayVolume::BEDROCK);
			}
		}
		(new RavineCarver(ArrayVolume::AIR, $this->carvableOnly(), 1, 1, 50, -50))->carve($volume, 0, 0, 4321);
		self::assertSame(ArrayVolume::BEDROCK, $volume->getBlockStateId(8, -64, 8));
	}
}
