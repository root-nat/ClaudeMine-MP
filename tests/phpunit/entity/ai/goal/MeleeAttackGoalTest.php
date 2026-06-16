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

namespace pocketmine\entity\ai\goal;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\ai\FakeMobContext;
use pocketmine\entity\ai\memory\MemoryModuleType;
use pocketmine\entity\ai\nav\GridNodeAccess;
use pocketmine\entity\ai\target\TargetCandidate;
use pocketmine\math\Vector3;

class MeleeAttackGoalTest extends TestCase{

	private function groundedMob(float $x, float $y, float $z) : FakeMobContext{
		$world = new GridNodeAccess();
		$world->fillFloor(-20, 20, -20, 20, 0);
		return new FakeMobContext(new Vector3($x, $y, $z), $world);
	}

	public function testCanUseRequiresAliveTarget() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$goal = new MeleeAttackGoal();
		self::assertFalse($goal->canUse($mob));

		$mob->getMemory()->set(MemoryModuleType::ATTACK_TARGET, new TargetCandidate(7, 5.5, 1, 0.5, true, true));
		self::assertTrue($goal->canUse($mob));

		$mob->getMemory()->set(MemoryModuleType::ATTACK_TARGET, new TargetCandidate(7, 5.5, 1, 0.5, false, true));
		self::assertFalse($goal->canUse($mob));
	}

	public function testAttacksWhenInReach() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$mob->getMemory()->set(MemoryModuleType::ATTACK_TARGET, new TargetCandidate(7, 1.5, 1, 0.5, true, true));
		$goal = new MeleeAttackGoal();
		$goal->start($mob);
		$goal->tick($mob);
		self::assertCount(1, $mob->attackCalls);
		self::assertSame(7, $mob->attackCalls[0]->entityId);
	}

	public function testRespectsAttackCooldown() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$mob->getMemory()->set(MemoryModuleType::ATTACK_TARGET, new TargetCandidate(7, 1.5, 1, 0.5, true, true));
		$goal = new MeleeAttackGoal();
		$goal->start($mob);
		//tick many times while in reach: only one hit until cooldown elapses
		for($i = 0; $i < 10; ++$i){
			$goal->tick($mob);
		}
		self::assertCount(1, $mob->attackCalls);
	}

	public function testMovesTowardDistantTarget() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$mob->getMemory()->set(MemoryModuleType::ATTACK_TARGET, new TargetCandidate(7, 6.5, 1, 0.5, true, true));
		$goal = new MeleeAttackGoal();
		$goal->start($mob);
		$goal->tick($mob);
		self::assertNotEmpty($mob->motionCalls, "Mob should add motion toward a distant target");
		self::assertNotEmpty($mob->lookAtCalls);
	}

	public function testDoesNotAttackOutOfReach() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$mob->getMemory()->set(MemoryModuleType::ATTACK_TARGET, new TargetCandidate(7, 6.5, 1, 0.5, true, true));
		$goal = new MeleeAttackGoal();
		$goal->start($mob);
		$goal->tick($mob);
		self::assertEmpty($mob->attackCalls);
	}
}
