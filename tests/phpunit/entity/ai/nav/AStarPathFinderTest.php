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

namespace pocketmine\entity\ai\nav;

use PHPUnit\Framework\TestCase;
use function abs;
use function count;

class AStarPathFinderTest extends TestCase{

	private function flatWorld(int $size = 10) : GridNodeAccess{
		$world = new GridNodeAccess();
		$world->fillFloor(-1, $size + 1, -1, $size + 1, 0);
		return $world;
	}

	public function testStraightLinePathOnOpenGround() : void{
		$world = $this->flatWorld();
		$finder = new AStarPathFinder();
		$path = $finder->findPath($world, 0, 1, 0, 5, 1, 0, false);

		self::assertNotNull($path);
		$points = $path->getPoints();
		self::assertSame([0, 1, 0], $points[0]);
		self::assertSame([5, 1, 0], $points[count($points) - 1]);
		//on open ground the path should be tight (no wandering): at most the Chebyshev distance + 1 nodes
		self::assertLessThanOrEqual(7, count($points));
	}

	public function testPathRoutesAroundWall() : void{
		$world = $this->flatWorld();
		//build a wall across x=3 from z=-1..4 leaving a gap further out, forcing a detour
		for($z = -1; $z <= 3; ++$z){
			$world->fillWall(3, $z, 1, 2);
		}
		$finder = new AStarPathFinder();
		$path = $finder->findPath($world, 0, 1, 0, 5, 1, 0, false);

		self::assertNotNull($path);
		//no waypoint may sit inside the wall column x=3 at the blocked z range
		foreach($path->getPoints() as [$x, $y, $z]){
			self::assertFalse($x === 3 && $z >= -1 && $z <= 3, "Path walked through the wall at $x,$y,$z");
		}
		self::assertSame([5, 1, 0], $path->getPoints()[count($path->getPoints()) - 1]);
	}

	public function testUnreachableTargetReturnsNullWithoutPartial() : void{
		$world = $this->flatWorld();
		//fully enclose the target cell (5,1,0) with a 4-wall box
		$world->fillWall(4, 0, 1, 2);
		$world->fillWall(6, 0, 1, 2);
		$world->fillWall(5, -1, 1, 2);
		$world->fillWall(5, 1, 1, 2);
		$finder = new AStarPathFinder();
		$path = $finder->findPath($world, 0, 1, 0, 5, 1, 0, false);
		self::assertNull($path);
	}

	public function testUnreachableTargetReturnsPartialPath() : void{
		$world = $this->flatWorld();
		$world->fillWall(4, 0, 1, 2);
		$world->fillWall(6, 0, 1, 2);
		$world->fillWall(5, -1, 1, 2);
		$world->fillWall(5, 1, 1, 2);
		$finder = new AStarPathFinder();
		$path = $finder->findPath($world, 0, 1, 0, 5, 1, 0, true);
		self::assertNotNull($path);
		//partial path should head toward but never reach the enclosed target
		self::assertNotSame([5, 1, 0], $path->getPoints()[count($path->getPoints()) - 1]);
	}

	public function testClimbsSingleStepStaircase() : void{
		$world = new GridNodeAccess();
		//staircase: floor rises by 1 each x step
		$world->setSolid(0, 0, 0);
		$world->setSolid(1, 1, 0);
		$world->setSolid(2, 2, 0);
		$world->setSolid(3, 3, 0);
		$finder = new AStarPathFinder();
		$path = $finder->findPath($world, 0, 1, 0, 3, 4, 0, false);

		self::assertNotNull($path);
		$points = $path->getPoints();
		self::assertSame([0, 1, 0], $points[0]);
		self::assertSame([3, 4, 0], $points[count($points) - 1]);
		//each successive waypoint climbs exactly one block
		for($i = 1; $i < count($points); ++$i){
			self::assertSame(1, $points[$i][1] - $points[$i - 1][1]);
		}
	}

	public function testStartNotStandableReturnsNull() : void{
		$world = new GridNodeAccess(); //no floor anywhere
		$finder = new AStarPathFinder();
		self::assertNull($finder->findPath($world, 0, 1, 0, 5, 1, 0, false));
	}

	public function testNodeBudgetBoundsSearch() : void{
		$world = $this->flatWorld(40);
		$finder = new AStarPathFinder(new NodeEvaluator(), 8); //tiny budget
		//a far target on open ground: strict mode can't reach it within 8 nodes
		$path = $finder->findPath($world, 0, 1, 0, 30, 1, 0, false);
		self::assertNull($path);
	}

	public function testPathDropsDownLedge() : void{
		$world = new GridNodeAccess();
		//high floor at x0-1, then a 2-block drop at x2
		$world->setSolid(0, 5, 0);
		$world->setSolid(1, 5, 0);
		$world->setSolid(2, 3, 0);
		$finder = new AStarPathFinder();
		$path = $finder->findPath($world, 0, 6, 0, 2, 4, 0, false);
		self::assertNotNull($path);
		self::assertSame([2, 4, 0], $path->getPoints()[count($path->getPoints()) - 1]);
	}
}
