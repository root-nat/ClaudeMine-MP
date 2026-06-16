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
use pocketmine\world\generator\carver\ArrayVolume;

class StructurePopulatorTest extends TestCase{
	private const COBBLE = 10;
	private const MOSSY = 11;
	private const SPAWNER = 12;
	private const CHEST = 13;

	private function dungeon() : DungeonStructure{
		return new DungeonStructure(ArrayVolume::AIR, self::COBBLE, self::MOSSY, self::SPAWNER, self::CHEST);
	}

	private function cavedVolume(int $minChunkX = -1, int $maxChunkX = 1, int $minChunkZ = -1, int $maxChunkZ = 1) : ArrayVolume{
		$volume = new ArrayVolume(-64, 320);
		$volume->loadRegion($minChunkX, $maxChunkX, $minChunkZ, $maxChunkZ);
		//carve a flat-floored air gallery through chunk (0,0) so an anchor exists there
		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				for($y = 10; $y < 16; ++$y){
					$volume->setBlockStateId($x, $y, $z, ArrayVolume::AIR);
				}
			}
		}
		return $volume;
	}

	/**
	 * @return string[] sorted "x:y:z" positions of every spawner in the volume
	 */
	private function spawnerPositions(ArrayVolume $volume) : array{
		$positions = [];
		for($x = -10; $x < 26; ++$x){
			for($z = -10; $z < 26; ++$z){
				for($y = 5; $y < 20; ++$y){
					if($volume->getBlockStateId($x, $y, $z) === self::SPAWNER){
						$positions[] = "$x:$y:$z";
					}
				}
			}
		}
		sort($positions);
		return $positions;
	}

	private function spawnerCount(ArrayVolume $volume) : int{
		$count = 0;
		for($x = -2; $x < 18; ++$x){
			for($z = -2; $z < 18; ++$z){
				for($y = 5; $y < 20; ++$y){
					if($volume->getBlockStateId($x, $y, $z) === self::SPAWNER){
						++$count;
					}
				}
			}
		}
		return $count;
	}

	public function testRarityGateBlocksMostChunks() : void{
		//vary the world seed so the per-region gate varies, always on the caved chunk 0,0
		$placements = 0;
		for($seed = 0; $seed < 40; ++$seed){
			$volume = $this->cavedVolume();
			$pop = new StructurePopulator($seed, $this->dungeon(), 8, -54, 14, ArrayVolume::AIR);
			if($pop->tryPlace($volume, 0, 0)){
				++$placements;
			}
		}
		//rarity 8 -> roughly 1/8 of seeds open the gate; far from all 40, but more than none
		self::assertLessThan(40, $placements);
		self::assertGreaterThan(0, $placements);
	}

	public function testPlacesDungeonWhenGateOpensAndAnchorExists() : void{
		//rarity 1 -> always attempts; the caved volume guarantees an anchor
		$volume = $this->cavedVolume();
		$pop = new StructurePopulator(123, $this->dungeon(), 1, -54, 14, ArrayVolume::AIR);
		self::assertTrue($pop->tryPlace($volume, 0, 0));
		self::assertSame(1, $this->spawnerCount($volume));
	}

	public function testNoAnchorNoPlacement() : void{
		//solid volume with no caves -> no cave floor anchor -> nothing placed even with rarity 1
		$volume = new ArrayVolume(-64, 320);
		$volume->loadRegion(-1, 1, -1, 1);
		$pop = new StructurePopulator(123, $this->dungeon(), 1, -54, 14, ArrayVolume::AIR);
		self::assertFalse($pop->tryPlace($volume, 0, 0));
		self::assertSame(0, $this->spawnerCount($volume));
	}

	public function testDeterministic() : void{
		$a = $this->cavedVolume();
		$b = $this->cavedVolume();
		(new StructurePopulator(55, $this->dungeon(), 1, -54, 14, ArrayVolume::AIR))->tryPlace($a, 0, 0);
		(new StructurePopulator(55, $this->dungeon(), 1, -54, 14, ArrayVolume::AIR))->tryPlace($b, 0, 0);
		self::assertSame($a->airCells(), $b->airCells());
	}

	public function testCrossChunkDeterminism() : void{
		//a dungeon anchored in chunk (0,0) must be reproduced identically whether we process chunk (0,0) or its
		//neighbour (1,0) - both iterate region (0,0) and re-derive the same dungeon (fixes the boundary seam bug)
		$fromCentre = $this->cavedVolume(-1, 2, -1, 1);
		$fromNeighbour = $this->cavedVolume(-1, 2, -1, 1);

		(new StructurePopulator(2025, $this->dungeon(), 1, -54, 14, ArrayVolume::AIR))->tryPlace($fromCentre, 0, 0);
		(new StructurePopulator(2025, $this->dungeon(), 1, -54, 14, ArrayVolume::AIR))->tryPlace($fromNeighbour, 1, 0);

		$centreSpawners = $this->spawnerPositions($fromCentre);
		self::assertNotEmpty($centreSpawners, "Region (0,0) should place a dungeon");
		self::assertSame($centreSpawners, $this->spawnerPositions($fromNeighbour), "Neighbour must re-derive the identical dungeon");
	}
}
