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

namespace pocketmine\entity\ai\memory;

use PHPUnit\Framework\TestCase;

class MemoryTest extends TestCase{

	public function testSetGetHasErase() : void{
		$memory = new Memory();
		self::assertFalse($memory->has(MemoryModuleType::WALK_TARGET));
		$memory->set(MemoryModuleType::WALK_TARGET, "value");
		self::assertTrue($memory->has(MemoryModuleType::WALK_TARGET));
		self::assertSame("value", $memory->get(MemoryModuleType::WALK_TARGET));
		$memory->erase(MemoryModuleType::WALK_TARGET);
		self::assertFalse($memory->has(MemoryModuleType::WALK_TARGET));
	}

	public function testDistinctModulesDoNotCollide() : void{
		$memory = new Memory();
		$memory->set(MemoryModuleType::WALK_TARGET, "a");
		$memory->set(MemoryModuleType::HOME, "b");
		self::assertSame("a", $memory->get(MemoryModuleType::WALK_TARGET));
		self::assertSame("b", $memory->get(MemoryModuleType::HOME));
	}

	public function testTtlExpires() : void{
		$memory = new Memory();
		$memory->set(MemoryModuleType::ATTACK_TARGET, "x", 5);
		$memory->tickExpiries(3);
		self::assertTrue($memory->has(MemoryModuleType::ATTACK_TARGET));
		$memory->tickExpiries(3);
		self::assertFalse($memory->has(MemoryModuleType::ATTACK_TARGET));
	}

	public function testNonTtlMemoryNeverExpires() : void{
		$memory = new Memory();
		$memory->set(MemoryModuleType::HOME, "persist");
		$memory->tickExpiries(100000);
		self::assertTrue($memory->has(MemoryModuleType::HOME));
	}

	public function testResettingValueClearsTtl() : void{
		$memory = new Memory();
		$memory->set(MemoryModuleType::WALK_TARGET, "ttl", 2);
		$memory->set(MemoryModuleType::WALK_TARGET, "permanent");
		$memory->tickExpiries(10);
		self::assertTrue($memory->has(MemoryModuleType::WALK_TARGET));
		self::assertSame("permanent", $memory->get(MemoryModuleType::WALK_TARGET));
	}
}
