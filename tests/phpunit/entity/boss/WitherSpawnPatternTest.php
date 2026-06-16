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

namespace pocketmine\entity\boss;

use PHPUnit\Framework\TestCase;
use function count;
use function in_array;

class WitherSpawnPatternTest extends TestCase{

	/**
	 * Builds skull/soul-sand predicates for a valid wither centred at (cx,cy,cz) along the given axis.
	 *
	 * @return array{\Closure, \Closure}
	 */
	private function structure(int $cx, int $cy, int $cz, bool $alongX) : array{
		$dx = $alongX ? 1 : 0;
		$dz = $alongX ? 0 : 1;
		$skulls = [];
		$soulSand = [];
		for($k = -1; $k <= 1; ++$k){
			$skulls[] = ($cx + $k * $dx) . ":" . $cy . ":" . ($cz + $k * $dz);
			$soulSand[] = ($cx + $k * $dx) . ":" . ($cy - 1) . ":" . ($cz + $k * $dz);
		}
		$soulSand[] = "$cx:" . ($cy - 2) . ":$cz";

		$isSkull = fn(int $x, int $y, int $z) => in_array("$x:$y:$z", $skulls, true);
		$isSoulSand = fn(int $x, int $y, int $z) => in_array("$x:$y:$z", $soulSand, true);
		return [$isSkull, $isSoulSand];
	}

	public function testDetectsAlongX() : void{
		[$isSkull, $isSoulSand] = $this->structure(10, 70, 5, true);
		//final skull placed on the centre head; alongX = true
		self::assertSame([10, 70, 5, true], WitherSpawnPattern::findCentre($isSkull, $isSoulSand, 10, 70, 5));
	}

	public function testDetectsAlongZ() : void{
		[$isSkull, $isSoulSand] = $this->structure(10, 70, 5, false);
		self::assertSame([10, 70, 5, false], WitherSpawnPattern::findCentre($isSkull, $isSoulSand, 10, 70, 5));
	}

	public function testDetectsWhenFinalSkullIsAnArm() : void{
		[$isSkull, $isSoulSand] = $this->structure(10, 70, 5, true);
		//final skull placed on the +X arm; the centre must still resolve to (10,70,5)
		self::assertSame([10, 70, 5, true], WitherSpawnPattern::findCentre($isSkull, $isSoulSand, 11, 70, 5));
		self::assertSame([10, 70, 5, true], WitherSpawnPattern::findCentre($isSkull, $isSoulSand, 9, 70, 5));
	}

	public function testMissingSkullFailsDetection() : void{
		[$isSkull, $isSoulSand] = $this->structure(10, 70, 5, true);
		//remove one skull by wrapping the predicate
		$brokenSkull = fn(int $x, int $y, int $z) => $x !== 11 && $isSkull($x, $y, $z);
		self::assertNull(WitherSpawnPattern::findCentre($brokenSkull, $isSoulSand, 10, 70, 5));
	}

	public function testMissingStemFailsDetection() : void{
		[$isSkull, $isSoulSand] = $this->structure(10, 70, 5, true);
		$brokenSoulSand = fn(int $x, int $y, int $z) => !($x === 10 && $y === 68 && $z === 5) && $isSoulSand($x, $y, $z);
		self::assertNull(WitherSpawnPattern::findCentre($isSkull, $brokenSoulSand, 10, 70, 5));
	}

	public function testEmptyAreaFailsDetection() : void{
		$never = fn(int $x, int $y, int $z) => false;
		self::assertNull(WitherSpawnPattern::findCentre($never, $never, 0, 70, 0));
	}

	public function testStructureBlocksCoversSevenBlocks() : void{
		$blocks = WitherSpawnPattern::structureBlocks([10, 70, 5], true);
		self::assertCount(7, $blocks); //3 skulls + 3 arms + 1 stem
		self::assertTrue(in_array([10, 68, 5], $blocks, true), "Stem must be included");
		self::assertTrue(in_array([9, 70, 5], $blocks, true) && in_array([11, 70, 5], $blocks, true));
	}

	public function testStructureBlocksAreUnique() : void{
		$blocks = WitherSpawnPattern::structureBlocks([0, 70, 0], false);
		$keys = [];
		foreach($blocks as [$x, $y, $z]){
			$keys["$x:$y:$z"] = true;
		}
		self::assertSame(count($blocks), count($keys));
	}
}
