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

namespace pocketmine\player;

use PHPUnit\Framework\TestCase;

class ShieldBlockingResolverTest extends TestCase{

	public function testBlocksAttackFromTheFront() : void{
		//facing south (yaw 0 = +Z); an attacker to the south is in front
		self::assertTrue(ShieldBlockingResolver::blocksAttackFrom(0.0, 0.0, 0.0, 0.0, 5.0));
	}

	public function testDoesNotBlockFromBehind() : void{
		//facing south; an attacker to the north is behind
		self::assertFalse(ShieldBlockingResolver::blocksAttackFrom(0.0, 0.0, 0.0, 0.0, -5.0));
	}

	public function testDoesNotBlockExactlyToTheSide() : void{
		//facing south; an attacker due east is at 90 degrees - not covered
		self::assertFalse(ShieldBlockingResolver::blocksAttackFrom(0.0, 0.0, 0.0, 5.0, 0.0));
	}

	public function testRespectsYaw() : void{
		//facing west (yaw 90 = -X); an attacker to the west is in front, one to the east is behind
		self::assertTrue(ShieldBlockingResolver::blocksAttackFrom(90.0, 0.0, 0.0, -5.0, 0.0));
		self::assertFalse(ShieldBlockingResolver::blocksAttackFrom(90.0, 0.0, 0.0, 5.0, 0.0));
	}

	public function testBlocksWhenAttackerAtSamePosition() : void{
		self::assertTrue(ShieldBlockingResolver::blocksAttackFrom(0.0, 3.0, 3.0, 3.0, 3.0));
	}
}
