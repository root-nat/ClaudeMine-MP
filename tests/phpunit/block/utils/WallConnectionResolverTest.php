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

namespace pocketmine\block\utils;

use PHPUnit\Framework\TestCase;
use pocketmine\math\Facing;
use function in_array;

class WallConnectionResolverTest extends TestCase{

	/**
	 * @param int[] $connectedFacings
	 * @return array<int, bool>
	 */
	private static function connected(array $connectedFacings) : array{
		$map = [];
		foreach(Facing::HORIZONTAL as $facing){
			$map[$facing] = in_array($facing, $connectedFacings, true);
		}
		return $map;
	}

	public function testIsolatedWallIsAPost() : void{
		[$connections, $post] = WallConnectionResolver::resolve(self::connected([]), false, false);
		self::assertSame([], $connections);
		self::assertTrue($post); //a wall touching nothing is just a post
	}

	public function testStraightNorthSouthHasNoPost() : void{
		[$connections, $post] = WallConnectionResolver::resolve(self::connected([Facing::NORTH, Facing::SOUTH]), false, false);
		self::assertCount(2, $connections);
		self::assertSame(WallConnectionType::SHORT, $connections[Facing::NORTH]);
		self::assertSame(WallConnectionType::SHORT, $connections[Facing::SOUTH]);
		self::assertFalse($post); //thin straight pass-through
	}

	public function testStraightEastWestHasNoPost() : void{
		[, $post] = WallConnectionResolver::resolve(self::connected([Facing::EAST, Facing::WEST]), false, false);
		self::assertFalse($post);
	}

	public function testCornerHasPost() : void{
		[, $post] = WallConnectionResolver::resolve(self::connected([Facing::NORTH, Facing::EAST]), false, false);
		self::assertTrue($post); //L-shape is not a straight pass-through
	}

	public function testTJunctionHasPost() : void{
		[, $post] = WallConnectionResolver::resolve(self::connected([Facing::NORTH, Facing::SOUTH, Facing::EAST]), false, false);
		self::assertTrue($post);
	}

	public function testCrossHasPost() : void{
		[$connections, $post] = WallConnectionResolver::resolve(self::connected([Facing::NORTH, Facing::SOUTH, Facing::EAST, Facing::WEST]), false, false);
		self::assertCount(4, $connections);
		self::assertTrue($post); //four-way junction keeps its post
	}

	public function testSingleConnectionHasPost() : void{
		[, $post] = WallConnectionResolver::resolve(self::connected([Facing::NORTH]), false, false);
		self::assertTrue($post);
	}

	public function testStackedStraightWallIsFlatPanelWithoutPost() : void{
		//a plain wall stacked on top covers the top (connections TALL) but does NOT force a post: the middle of a stacked
		//wall must stay a flat panel, with posts only at the ends. This is the desired (screen 2) behaviour.
		[$connections, $post] = WallConnectionResolver::resolve(self::connected([Facing::NORTH, Facing::SOUTH]), true, false);
		self::assertSame(WallConnectionType::TALL, $connections[Facing::NORTH]);
		self::assertSame(WallConnectionType::TALL, $connections[Facing::SOUTH]);
		self::assertFalse($post);
	}

	public function testForcingBlockAboveRaisesPostOnStraightWall() : void{
		//a full solid block, or a wall that is itself a post, forces a centre post even on a straight wall
		[$connections, $post] = WallConnectionResolver::resolve(self::connected([Facing::NORTH, Facing::SOUTH]), true, true);
		self::assertSame(WallConnectionType::TALL, $connections[Facing::NORTH]);
		self::assertTrue($post);
	}

	public function testTorchAboveForcesPostButKeepsConnectionsShort() : void{
		//a torch/pressure plate/sign on the wall forces the centre post but does not cover the top, so the side
		//connections stay SHORT (aboveCoversTop=false, aboveForcesPost=true)
		[$connections, $post] = WallConnectionResolver::resolve(self::connected([Facing::NORTH, Facing::SOUTH]), false, true);
		self::assertSame(WallConnectionType::SHORT, $connections[Facing::NORTH]);
		self::assertSame(WallConnectionType::SHORT, $connections[Facing::SOUTH]);
		self::assertTrue($post);
	}

	public function testCrossWithFullBlockAboveIsAllTall() : void{
		[$connections, $post] = WallConnectionResolver::resolve(self::connected([Facing::NORTH, Facing::SOUTH, Facing::EAST, Facing::WEST]), true, true);
		foreach(Facing::HORIZONTAL as $facing){
			self::assertSame(WallConnectionType::TALL, $connections[$facing]);
		}
		self::assertTrue($post);
	}

	public function testHeightFollowsAboveCoverageNotPost() : void{
		$sides = self::connected([Facing::NORTH]);
		self::assertSame(WallConnectionType::SHORT, WallConnectionResolver::resolve($sides, false, false)[0][Facing::NORTH]);
		self::assertSame(WallConnectionType::TALL, WallConnectionResolver::resolve($sides, true, false)[0][Facing::NORTH]);
	}
}
