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

class BehaviourGoalsTest extends TestCase{

	private function groundedMob(float $x, float $y, float $z) : FakeMobContext{
		$world = new GridNodeAccess();
		$world->fillFloor(-30, 30, -30, 30, 0);
		//always-start RNG for goals gated on a probability
		return new FakeMobContext(new Vector3($x, $y, $z), $world, [0.0]);
	}

	public function testLookAtPlayerLooksAtNearestPlayer() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$mob->getMemory()->set(MemoryModuleType::NEAREST_PLAYERS, [
			new TargetCandidate(2, 3.5, 1, 0.5, true, true)
		]);
		$goal = new LookAtPlayerGoal();
		self::assertTrue($goal->canUse($mob));
		$goal->start($mob);
		$goal->tick($mob);
		self::assertNotEmpty($mob->lookAtCalls);
	}

	public function testLookAtPlayerIgnoresOutOfRange() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$mob->getMemory()->set(MemoryModuleType::NEAREST_PLAYERS, [
			new TargetCandidate(2, 50, 1, 0.5, true, true)
		]);
		$goal = new LookAtPlayerGoal();
		self::assertFalse($goal->canUse($mob));
	}

	public function testPanicFleesFromThreat() : void{
		$mob = $this->groundedMob(10.5, 1, 10.5);
		//threat to the west/south; mob should path away (east/north)
		$mob->getMemory()->set(MemoryModuleType::HURT_BY, new TargetCandidate(9, 5.5, 1, 5.5, true, true));
		$goal = new PanicGoal();
		self::assertTrue($goal->canUse($mob));
		$goal->start($mob);
		$goal->tick($mob);
		self::assertNotEmpty($mob->motionCalls, "Panicking mob should move");
		self::assertNotEmpty($mob->moveDirectionCalls, "Fleeing mob should face the way it flees");
		[$mdx, $mdz] = $mob->moveDirectionCalls[0];
		[$fx, , $fz] = $mob->motionCalls[0];
		self::assertSame($fx <=> 0.0, $mdx <=> 0.0, "Heading X must match applied motion X");
		self::assertSame($fz <=> 0.0, $mdz <=> 0.0, "Heading Z must match applied motion Z");
	}

	public function testStrollFacesMovementDirection() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$goal = new RandomStrollGoal();
		self::assertTrue($goal->canUse($mob));
		$goal->start($mob);
		$goal->tick($mob);
		self::assertNotEmpty($mob->motionCalls, "Strolling mob should move");
		self::assertNotEmpty($mob->moveDirectionCalls, "Strolling mob should face its movement direction");
		[$mdx, $mdz] = $mob->moveDirectionCalls[0];
		[$fx, , $fz] = $mob->motionCalls[0];
		self::assertSame($fx <=> 0.0, $mdx <=> 0.0, "Heading X must match applied motion X");
		self::assertSame($fz <=> 0.0, $mdz <=> 0.0, "Heading Z must match applied motion Z");
	}

	public function testPanicNeedsThreat() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$goal = new PanicGoal();
		self::assertFalse($goal->canUse($mob));
	}

	public function testRangedAttackShootsWhenInRange() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$mob->getMemory()->set(MemoryModuleType::ATTACK_TARGET, new TargetCandidate(3, 8.5, 1, 0.5, true, true));
		$shots = [];
		$goal = new RangedAttackGoal(function(TargetCandidate $t) use (&$shots) : void{
			$shots[] = $t;
		});
		$goal->tick($mob);
		self::assertCount(1, $shots);
		self::assertSame(3, $shots[0]->entityId);
	}

	public function testRangedAttackRespectsCooldown() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$mob->getMemory()->set(MemoryModuleType::ATTACK_TARGET, new TargetCandidate(3, 8.5, 1, 0.5, true, true));
		$shots = [];
		$goal = new RangedAttackGoal(function(TargetCandidate $t) use (&$shots) : void{
			$shots[] = $t;
		});
		for($i = 0; $i < 10; ++$i){
			$goal->tick($mob);
		}
		self::assertCount(1, $shots, "Only one shot should fire before the cooldown elapses");
	}

	public function testRangedAttackBacksAwayWhenTooClose() : void{
		$mob = $this->groundedMob(5.5, 1, 5.5);
		$mob->getMemory()->set(MemoryModuleType::ATTACK_TARGET, new TargetCandidate(3, 6.5, 1, 5.5, true, true));
		$goal = new RangedAttackGoal(fn(TargetCandidate $t) => null, 12.0, 4.0);
		$goal->tick($mob);
		self::assertNotEmpty($mob->motionCalls, "Should back away when target is within min range");
		//moving away from the east target means negative X motion
		self::assertLessThan(0.0, $mob->motionCalls[0][0]);
	}

	public function testTemptFollowsTemptingPlayer() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$mob->getMemory()->set(MemoryModuleType::TEMPTING_PLAYER, new TargetCandidate(7, 8.5, 1, 0.5, true, true));
		$goal = new TemptGoal();
		self::assertTrue($goal->canUse($mob));
		$goal->start($mob);
		$goal->tick($mob);
		self::assertNotEmpty($mob->lookAtCalls, "A tempted animal should look at the player");
		self::assertNotEmpty($mob->motionCalls, "A tempted animal should move toward the player");
	}

	public function testTemptStopsMovingWhenClose() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$mob->getMemory()->set(MemoryModuleType::TEMPTING_PLAYER, new TargetCandidate(7, 1.5, 1, 0.5, true, true));
		$goal = new TemptGoal();
		$goal->tick($mob);
		self::assertNotEmpty($mob->lookAtCalls, "Still looks at the nearby player");
		self::assertEmpty($mob->motionCalls, "Does not move once close enough");
	}

	public function testTemptInactiveWithoutTemptingPlayer() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		self::assertFalse((new TemptGoal())->canUse($mob));
	}

	public function testBreedGoalApproachesMate() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$mob->getMemory()->set(MemoryModuleType::BREED_TARGET, new TargetCandidate(9, 6.5, 1, 0.5, true, false));
		$goal = new BreedGoal();
		self::assertTrue($goal->canUse($mob));
		$goal->tick($mob);
		self::assertNotEmpty($mob->lookAtCalls, "Should look at the mate");
		self::assertNotEmpty($mob->motionCalls, "Should move toward the mate");
	}

	public function testFollowParentApproachesParent() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$mob->getMemory()->set(MemoryModuleType::PARENT, new TargetCandidate(9, 7.5, 1, 0.5, true, false));
		$goal = new FollowParentGoal();
		self::assertTrue($goal->canUse($mob));
		$goal->tick($mob);
		self::assertNotEmpty($mob->motionCalls, "A baby should move toward its parent");
	}

	public function testBreedAndFollowInactiveWithoutTarget() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		self::assertFalse((new BreedGoal())->canUse($mob));
		self::assertFalse((new FollowParentGoal())->canUse($mob));
	}

	public function testRangedAttackDrawsBeforeFiring() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$mob->getMemory()->set(MemoryModuleType::ATTACK_TARGET, new TargetCandidate(3, 8.5, 1, 0.5, true, true));
		$shots = [];
		$draws = [];
		$goal = new RangedAttackGoal(
			function(TargetCandidate $t) use (&$shots) : void{ $shots[] = $t; },
			12.0, 4.0, 30, 3,
			function(bool $d) use (&$draws) : void{ $draws[] = $d; }
		);
		$goal->tick($mob);
		self::assertCount(0, $shots, "No arrow is loosed during the draw windup");
		self::assertSame([true], $draws, "The bow draw begins on the first in-range tick");
		$goal->tick($mob);
		$goal->tick($mob);
		self::assertCount(0, $shots, "Still drawing");
		$goal->tick($mob);
		self::assertCount(1, $shots, "The arrow looses once the draw completes");
		self::assertSame([true, false], $draws, "Aiming stops on release");
	}

	public function testSlimeHopsTowardTarget() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$mob->getMemory()->set(MemoryModuleType::ATTACK_TARGET, new TargetCandidate(9, 8.5, 1, 0.5, true, true));
		$goal = new SlimeHopGoal(fn() : array => [12, 10, 0.3]);
		self::assertTrue($goal->canUse($mob));
		$goal->tick($mob);
		self::assertNotEmpty($mob->lookAtCalls, "Slime should look at its target");
		self::assertSame(1, $mob->jumpCalls, "Slime moves by jumping, not gliding");
		self::assertNotEmpty($mob->motionCalls, "The hop carries a horizontal impulse");
		self::assertGreaterThan(0.0, $mob->motionCalls[0][0], "Hop should head toward the +X target");
		self::assertSame(0.0, $mob->motionCalls[0][1], "The lift comes from jump(), not the horizontal impulse");
	}

	public function testSlimeDoesNotHopWhileAirborne() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$mob->onGround = false;
		$mob->getMemory()->set(MemoryModuleType::ATTACK_TARGET, new TargetCandidate(9, 8.5, 1, 0.5, true, true));
		$goal = new SlimeHopGoal(fn() : array => [12, 10, 0.3]);
		$goal->tick($mob);
		self::assertSame(0, $mob->jumpCalls, "No new hop is started mid-leap");
		self::assertEmpty($mob->motionCalls, "No gliding impulse while airborne");
	}

	public function testSlimeWandersWhenNoTarget() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$goal = new SlimeHopGoal(fn() : array => [12, 10, 0.3]);
		$goal->tick($mob);
		self::assertSame(1, $mob->jumpCalls, "An idle slime keeps bouncing around");
		self::assertNotEmpty($mob->motionCalls, "Idle hop still has a heading");
		self::assertEmpty($mob->lookAtCalls, "Nothing to look at while wandering");
	}

	public function testSlimeAttacksTargetOnContact() : void{
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$mob->getMemory()->set(MemoryModuleType::ATTACK_TARGET, new TargetCandidate(9, 1.5, 1, 0.5, true, true));
		$goal = new SlimeHopGoal(fn() : array => [12, 10, 0.3]);
		$goal->tick($mob);
		self::assertCount(1, $mob->attackCalls, "Slime deals contact damage when it lands on its target");
	}

	public function testSlimeHopUsesLiveSizeParams() : void{
		//a split child is resized AFTER its goals are built, so the goal must read hop params live, not from build time
		$size = 4;
		$mob = $this->groundedMob(0.5, 1, 0.5);
		$goal = new SlimeHopGoal(function() use (&$size) : array{
			return [12, 0, $size === 4 ? 0.36 : 0.24];
		});
		$goal->tick($mob);
		$largeSpeed = $mob->motionCalls[0][0];

		$size = 1; //shrink, then let the hop cooldown elapse and hop again
		for($i = 0; $i < 20; ++$i){
			$goal->tick($mob);
		}
		$last = end($mob->motionCalls);
		self::assertGreaterThan($last[0], $largeSpeed, "Hop speed must follow the live size, not the construction-time size");
	}

	public function testTemptApproachesDirectlyWhenNoPathFound() : void{
		//empty grid (no floor) => the pathfinder finds nothing; the animal must still approach, not freeze while looking
		$mob = new FakeMobContext(new Vector3(0.5, 1, 0.5));
		$mob->getMemory()->set(MemoryModuleType::TEMPTING_PLAYER, new TargetCandidate(7, 8.5, 1, 0.5, true, true));
		$goal = new TemptGoal();
		$goal->tick($mob);
		self::assertNotEmpty($mob->motionCalls, "Animal should still move toward the player without a navigable path");
		self::assertGreaterThan(0.0, $mob->motionCalls[0][0], "Should head toward the +X player");
		self::assertNotEmpty($mob->moveDirectionCalls, "Should face its movement direction");
	}
}
