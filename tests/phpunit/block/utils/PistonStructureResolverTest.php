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
use pocketmine\math\Vector3;
use function count;

class PistonStructureResolverTest extends TestCase{

	/**
	 * @param array<string, int> $grid maps "x:y:z" to a REACTION_* constant; unlisted cells default to air
	 */
	private function resolver(array $grid) : \Closure{
		return function(Vector3 $pos) use ($grid) : int{
			$key = $pos->getFloorX() . ":" . $pos->getFloorY() . ":" . $pos->getFloorZ();
			return $grid[$key] ?? PistonStructureResolver::REACTION_AIR;
		};
	}

	public function testEmptyPushMovesNothing() : void{
		$piston = new Vector3(0, 0, 0);
		$origin = $piston->getSide(Facing::EAST);
		$result = PistonStructureResolver::computeMovement($origin, $piston, Facing::EAST, $this->resolver([]));
		self::assertNotNull($result);
		[$move, $destroy] = $result;
		self::assertCount(0, $move);
		self::assertCount(0, $destroy);
	}

	public function testPushesSingleBlock() : void{
		$piston = new Vector3(0, 0, 0);
		$result = PistonStructureResolver::computeMovement(new Vector3(1, 0, 0), $piston, Facing::EAST, $this->resolver([
			"1:0:0" => PistonStructureResolver::REACTION_NORMAL
		]));
		self::assertNotNull($result);
		[$move, $destroy] = $result;
		self::assertCount(1, $move);
		self::assertTrue($move[0]->equals(new Vector3(1, 0, 0)));
		self::assertCount(0, $destroy);
	}

	public function testPushesBlockLine() : void{
		$piston = new Vector3(0, 0, 0);
		$grid = [];
		for($x = 1; $x <= 5; ++$x){
			$grid["$x:0:0"] = PistonStructureResolver::REACTION_NORMAL;
		}
		$result = PistonStructureResolver::computeMovement(new Vector3(1, 0, 0), $piston, Facing::EAST, $this->resolver($grid));
		self::assertNotNull($result);
		[$move] = $result;
		self::assertCount(5, $move);
	}

	public function testImmovableBlockFailsPush() : void{
		$piston = new Vector3(0, 0, 0);
		$result = PistonStructureResolver::computeMovement(new Vector3(1, 0, 0), $piston, Facing::EAST, $this->resolver([
			"1:0:0" => PistonStructureResolver::REACTION_NORMAL,
			"2:0:0" => PistonStructureResolver::REACTION_BLOCK
		]));
		self::assertNull($result);
	}

	public function testExceedingPushLimitFails() : void{
		$piston = new Vector3(0, 0, 0);
		$grid = [];
		for($x = 1; $x <= 13; ++$x){
			$grid["$x:0:0"] = PistonStructureResolver::REACTION_NORMAL;
		}
		$result = PistonStructureResolver::computeMovement(new Vector3(1, 0, 0), $piston, Facing::EAST, $this->resolver($grid));
		self::assertNull($result);
	}

	public function testExactlyTwelveBlocksSucceeds() : void{
		$piston = new Vector3(0, 0, 0);
		$grid = [];
		for($x = 1; $x <= 12; ++$x){
			$grid["$x:0:0"] = PistonStructureResolver::REACTION_NORMAL;
		}
		$result = PistonStructureResolver::computeMovement(new Vector3(1, 0, 0), $piston, Facing::EAST, $this->resolver($grid));
		self::assertNotNull($result);
		[$move] = $result;
		self::assertCount(12, $move);
	}

	public function testDestroyableBlockIsDestroyedNotMoved() : void{
		$piston = new Vector3(0, 0, 0);
		$result = PistonStructureResolver::computeMovement(new Vector3(1, 0, 0), $piston, Facing::EAST, $this->resolver([
			"1:0:0" => PistonStructureResolver::REACTION_DESTROY
		]));
		self::assertNotNull($result);
		[$move, $destroy] = $result;
		self::assertCount(0, $move);
		self::assertCount(1, $destroy);
	}

	public function testStickyBlockDragsPerpendicularNeighbour() : void{
		$piston = new Vector3(0, 0, 0);
		//slime at (1,0,0) with a normal block stuck above it at (1,1,0)
		$result = PistonStructureResolver::computeMovement(new Vector3(1, 0, 0), $piston, Facing::EAST, $this->resolver([
			"1:0:0" => PistonStructureResolver::REACTION_STICKY,
			"1:1:0" => PistonStructureResolver::REACTION_NORMAL
		]));
		self::assertNotNull($result);
		[$move] = $result;
		self::assertCount(2, $move);
	}

	public function testStickyAdhesionDoesNotCollectPiston() : void{
		$piston = new Vector3(0, 0, 0);
		//slime directly in front of the piston must not try to drag the piston itself backward
		$result = PistonStructureResolver::computeMovement(new Vector3(1, 0, 0), $piston, Facing::EAST, $this->resolver([
			"1:0:0" => PistonStructureResolver::REACTION_STICKY
		]));
		self::assertNotNull($result);
		[$move] = $result;
		self::assertCount(1, $move);
	}

	public function testStickyChainBlockedByImmovableNeighbour() : void{
		$piston = new Vector3(0, 0, 0);
		$result = PistonStructureResolver::computeMovement(new Vector3(1, 0, 0), $piston, Facing::EAST, $this->resolver([
			"1:0:0" => PistonStructureResolver::REACTION_STICKY,
			"1:1:0" => PistonStructureResolver::REACTION_BLOCK
		]));
		self::assertNull($result);
	}
}
