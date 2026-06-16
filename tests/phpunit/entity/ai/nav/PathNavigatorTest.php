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
use pocketmine\math\Vector3;

class PathNavigatorTest extends TestCase{

	public function testIdleWhenNoPath() : void{
		$nav = new PathNavigator();
		$intent = $nav->tick(new Vector3(0, 1, 0));
		self::assertTrue($intent->arrived);
		self::assertFalse($intent->hasMovement());
	}

	public function testWalksTowardWaypoint() : void{
		$nav = new PathNavigator();
		$nav->setPath(new Path([[0, 1, 0], [3, 1, 0]]));
		//standing at the first node centre, should head +X toward the second node
		$intent = $nav->tick(new Vector3(0.5, 1, 0.5));
		self::assertFalse($intent->arrived);
		self::assertGreaterThan(0.9, $intent->dirX);
		self::assertEqualsWithDelta(0.0, $intent->dirZ, 0.01);
	}

	public function testDirectionIsUnitLength() : void{
		$nav = new PathNavigator();
		$nav->setPath(new Path([[0, 1, 0], [3, 1, 3]]));
		$intent = $nav->tick(new Vector3(0.5, 1, 0.5));
		$magnitude = sqrt($intent->dirX ** 2 + $intent->dirZ ** 2);
		self::assertEqualsWithDelta(1.0, $magnitude, 0.001);
	}

	public function testAdvancesCursorOnArrival() : void{
		$nav = new PathNavigator();
		$path = new Path([[0, 1, 0], [1, 1, 0], [2, 1, 0]]);
		$nav->setPath($path);
		//positioned exactly on the first waypoint -> should advance and aim at the second
		$nav->tick(new Vector3(0.5, 1, 0.5));
		self::assertGreaterThanOrEqual(1, $path->getCursor());
	}

	public function testArrivesAtFinalWaypoint() : void{
		$nav = new PathNavigator();
		$nav->setPath(new Path([[0, 1, 0], [1, 1, 0]]));
		//tick 1 at the start node consumes it; tick 2 at the final node should report arrival
		$nav->tick(new Vector3(0.5, 1, 0.5));
		$intent = $nav->tick(new Vector3(1.5, 1, 0.5));
		self::assertTrue($intent->arrived);
	}

	public function testJumpFlagWhenNextWaypointHigher() : void{
		$nav = new PathNavigator();
		$nav->setPath(new Path([[0, 1, 0], [1, 2, 0]]));
		$intent = $nav->tick(new Vector3(0.5, 1, 0.5));
		self::assertTrue($intent->jump);
	}

	public function testNoJumpOnFlatPath() : void{
		$nav = new PathNavigator();
		$nav->setPath(new Path([[0, 1, 0], [1, 1, 0]]));
		$intent = $nav->tick(new Vector3(0.5, 1, 0.5));
		self::assertFalse($intent->jump);
	}

	public function testRequestsRepathWhenStuck() : void{
		$nav = new PathNavigator();
		$nav->setPath(new Path([[0, 1, 0], [10, 1, 0]]));
		$stuckPos = new Vector3(0.5, 1, 0.5);
		$repathRequested = false;
		for($i = 0; $i < 60; ++$i){
			$intent = $nav->tick($stuckPos); //never moves
			if($intent->needsRepath){
				$repathRequested = true;
				break;
			}
		}
		self::assertTrue($repathRequested);
	}
}
