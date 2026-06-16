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
use pocketmine\utils\Random;
use pocketmine\world\generator\carver\ArrayVolume;

class NetherFortressStructureTest extends TestCase{
	private const BRICK = 10;
	private const FENCE = 11;
	private const SOUL = 12;
	private const WART = 13;
	private const SPAWNER = 14;

	private const ANCHOR_Y = 40;
	private const R = NetherFortressStructure::MAX_RADIUS;

	private function structure() : NetherFortressStructure{
		return new NetherFortressStructure(
			ArrayVolume::AIR,
			self::BRICK,
			self::FENCE,
			self::SOUL,
			self::WART,
			self::SPAWNER,
			[Facing::NORTH => 20, Facing::SOUTH => 21, Facing::EAST => 22, Facing::WEST => 23],
			[Facing::NORTH => 30, Facing::SOUTH => 31, Facing::EAST => 32, Facing::WEST => 33]
		);
	}

	private function loadedVolume() : ArrayVolume{
		$volume = new ArrayVolume(-64, 320);
		//the fortress reaches MAX_RADIUS blocks from the anchor at (0, *, 0): load every chunk it can touch
		$volume->loadRegion(-3, 3, -3, 3);
		return $volume;
	}

	/**
	 * @return int Count of cells equal to $stateId within the fortress footprint.
	 */
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

	public function testPlacesNetherBricksAndExactlyOneSpawner() : void{
		$volume = $this->loadedVolume();
		$this->structure()->place($volume, 0, self::ANCHOR_Y, 0, new Random(101));

		self::assertGreaterThan(50, $this->tally($volume, self::BRICK), "Fortress must lay a substantial nether-brick footprint");
		self::assertSame(1, $this->tally($volume, self::SPAWNER), "Exactly one blaze spawner on the balcony");
	}

	public function testHasFenceRailings() : void{
		$volume = $this->loadedVolume();
		$this->structure()->place($volume, 0, self::ANCHOR_Y, 0, new Random(7));
		self::assertGreaterThan(0, $this->tally($volume, self::FENCE), "Bridges and the junction must have fence railings");
	}

	public function testNetherWartSitsOnSoulSand() : void{
		$volume = $this->loadedVolume();
		$this->structure()->place($volume, 0, self::ANCHOR_Y, 0, new Random(55));

		$wart = 0;
		for($x = -self::R; $x <= self::R; ++$x){
			for($z = -self::R; $z <= self::R; ++$z){
				for($y = self::ANCHOR_Y; $y <= self::ANCHOR_Y + 2; ++$y){
					if($volume->getBlockStateId($x, $y, $z) === self::WART){
						++$wart;
						self::assertSame(self::SOUL, $volume->getBlockStateId($x, $y - 1, $z), "Nether wart must grow on soul sand");
					}
				}
			}
		}
		self::assertGreaterThan(0, $wart, "The wart room must contain nether wart");
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
		//anchor near the chunk edge so most of the fortress would overflow into unloaded chunks; place() must clip silently
		$this->structure()->place($volume, 8, self::ANCHOR_Y, 8, new Random(5));

		//the anchor's own junction floor lands inside the loaded chunk
		self::assertSame(self::BRICK, $volume->getBlockStateId(8, self::ANCHOR_Y, 8));
		//nothing was written into an unloaded neighbour chunk (no exception, no leakage)
		self::assertSame(ArrayVolume::AIR, $volume->getBlockStateId(40, self::ANCHOR_Y, 40));
	}
}
