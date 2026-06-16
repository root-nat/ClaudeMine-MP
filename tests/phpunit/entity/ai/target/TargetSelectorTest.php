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

namespace pocketmine\entity\ai\target;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\ai\FakeMobContext;
use pocketmine\entity\ai\memory\MemoryModuleType;
use pocketmine\math\Vector3;

class TargetSelectorTest extends TestCase{

	private function mobAt(float $x, float $y, float $z) : FakeMobContext{
		return new FakeMobContext(new Vector3($x, $y, $z));
	}

	public function testPicksNearestInRange() : void{
		$mob = $this->mobAt(0, 0, 0);
		$mob->getMemory()->set(MemoryModuleType::NEAREST_ENTITIES, [
			new TargetCandidate(10, 8, 0, 0, true, true),
			new TargetCandidate(11, 3, 0, 0, true, true),
			new TargetCandidate(12, 12, 0, 0, true, true)
		]);
		$selector = new TargetSelector();
		$target = $selector->selectTarget($mob);
		self::assertNotNull($target);
		self::assertSame(11, $target->entityId);
		self::assertSame(11, $mob->getMemory()->get(MemoryModuleType::ATTACK_TARGET)->entityId);
	}

	public function testIgnoresCandidatesBeyondFollowRange() : void{
		$mob = $this->mobAt(0, 0, 0);
		$mob->followRange = 5.0;
		$mob->getMemory()->set(MemoryModuleType::NEAREST_ENTITIES, [
			new TargetCandidate(10, 20, 0, 0, true, true)
		]);
		$selector = new TargetSelector();
		self::assertNull($selector->selectTarget($mob));
		self::assertFalse($mob->getMemory()->has(MemoryModuleType::ATTACK_TARGET));
	}

	public function testFiltersDeadCandidates() : void{
		$mob = $this->mobAt(0, 0, 0);
		$mob->getMemory()->set(MemoryModuleType::NEAREST_ENTITIES, [
			new TargetCandidate(10, 2, 0, 0, false, true),
			new TargetCandidate(11, 4, 0, 0, true, true)
		]);
		$selector = new TargetSelector();
		$target = $selector->selectTarget($mob);
		self::assertNotNull($target);
		self::assertSame(11, $target->entityId);
	}

	public function testHurtByPreferredOverNearest() : void{
		$mob = $this->mobAt(0, 0, 0);
		$mob->getMemory()->set(MemoryModuleType::NEAREST_ENTITIES, [
			new TargetCandidate(10, 2, 0, 0, true, true)
		]);
		$mob->getMemory()->set(MemoryModuleType::HURT_BY, new TargetCandidate(99, 6, 0, 0, true, true));
		$selector = new TargetSelector();
		$target = $selector->selectTarget($mob);
		self::assertNotNull($target);
		self::assertSame(99, $target->entityId);
	}

	public function testNoCandidatesErasesAttackTarget() : void{
		$mob = $this->mobAt(0, 0, 0);
		$mob->getMemory()->set(MemoryModuleType::ATTACK_TARGET, new TargetCandidate(5, 1, 0, 0, true, true));
		$selector = new TargetSelector();
		self::assertNull($selector->selectTarget($mob));
		self::assertFalse($mob->getMemory()->has(MemoryModuleType::ATTACK_TARGET));
	}
}
