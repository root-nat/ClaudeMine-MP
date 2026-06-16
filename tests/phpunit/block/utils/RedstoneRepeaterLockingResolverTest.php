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

class RedstoneRepeaterLockingResolverTest extends TestCase{

	public function testPerpendicularSidesAreCrossAxis() : void{
		self::assertSame([Facing::EAST, Facing::WEST], RedstoneRepeaterLockingResolver::perpendicularSides(Facing::NORTH));
		self::assertSame([Facing::EAST, Facing::WEST], RedstoneRepeaterLockingResolver::perpendicularSides(Facing::SOUTH));
		self::assertSame([Facing::NORTH, Facing::SOUTH], RedstoneRepeaterLockingResolver::perpendicularSides(Facing::EAST));
		self::assertSame([Facing::NORTH, Facing::SOUTH], RedstoneRepeaterLockingResolver::perpendicularSides(Facing::WEST));
	}

	public function testNeighbourLocksOnlyWhenPoweredAndFacingIn() : void{
		//a source east of the repeater locks it only if it faces EAST (output pointing west, back at the repeater)
		self::assertTrue(RedstoneRepeaterLockingResolver::neighbourLocks(Facing::EAST, true, Facing::EAST));
		self::assertFalse(RedstoneRepeaterLockingResolver::neighbourLocks(Facing::EAST, false, Facing::EAST), "Unpowered source can't lock");
		self::assertFalse(RedstoneRepeaterLockingResolver::neighbourLocks(Facing::EAST, true, Facing::WEST), "Facing away doesn't lock");
		self::assertFalse(RedstoneRepeaterLockingResolver::neighbourLocks(Facing::EAST, true, null), "Non-repeater/comparator side");
	}

	public function testLockedByPerpendicularSource() : void{
		//repeater faces north; a powered source on its east side facing east locks it
		self::assertTrue(RedstoneRepeaterLockingResolver::isLocked(Facing::NORTH, [
			Facing::EAST => ['powered' => true, 'facing' => Facing::EAST],
		]));
		self::assertTrue(RedstoneRepeaterLockingResolver::isLocked(Facing::NORTH, [
			Facing::WEST => ['powered' => true, 'facing' => Facing::WEST],
		]));
	}

	public function testNotLockedByInlineOrWrongFacingSource() : void{
		//a source directly in front/behind (on the repeater's own axis) never locks it
		self::assertFalse(RedstoneRepeaterLockingResolver::isLocked(Facing::NORTH, [
			Facing::NORTH => ['powered' => true, 'facing' => Facing::NORTH],
			Facing::SOUTH => ['powered' => true, 'facing' => Facing::SOUTH],
		]));
		//perpendicular but facing the wrong way (output not into the repeater)
		self::assertFalse(RedstoneRepeaterLockingResolver::isLocked(Facing::NORTH, [
			Facing::EAST => ['powered' => true, 'facing' => Facing::WEST],
		]));
		self::assertFalse(RedstoneRepeaterLockingResolver::isLocked(Facing::NORTH, []));
	}
}
