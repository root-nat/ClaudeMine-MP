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

class GoalSelectorTest extends TestCase{

	public function testDisjointFlagGoalsRunConcurrently() : void{
		$mob = new FakeMobContext();
		$selector = new GoalSelector();
		$move = new FakeGoal(GoalFlag::MOVE);
		$look = new FakeGoal(GoalFlag::LOOK);
		$selector->add(1, $move);
		$selector->add(2, $look);

		$selector->tick($mob);

		self::assertTrue($move->running);
		self::assertTrue($look->running);
		self::assertSame(1, $move->tickCount);
		self::assertSame(1, $look->tickCount);
	}

	public function testHigherPriorityPreemptsSharedFlag() : void{
		$mob = new FakeMobContext();
		$selector = new GoalSelector();
		$low = new FakeGoal(GoalFlag::MOVE);
		$high = new FakeGoal(GoalFlag::MOVE);
		$high->usable = false;
		$selector->add(5, $low);  //less important
		$selector->add(1, $high); //more important

		$selector->tick($mob); //only low can run
		self::assertTrue($low->running);
		self::assertFalse($high->running);

		$high->usable = true;
		$selector->tick($mob); //high preempts low
		self::assertTrue($high->running);
		self::assertFalse($low->running);
		self::assertSame(1, $low->stopCount);
	}

	public function testLowerPriorityCannotPreemptHigher() : void{
		$mob = new FakeMobContext();
		$selector = new GoalSelector();
		$high = new FakeGoal(GoalFlag::MOVE);
		$low = new FakeGoal(GoalFlag::MOVE);
		$selector->add(1, $high);
		$selector->add(5, $low);

		$selector->tick($mob);
		self::assertTrue($high->running);
		self::assertFalse($low->running);
	}

	public function testGoalStoppedWhenCanContinueFalse() : void{
		$mob = new FakeMobContext();
		$selector = new GoalSelector();
		$goal = new FakeGoal(GoalFlag::MOVE);
		$selector->add(1, $goal);

		$selector->tick($mob);
		self::assertTrue($goal->running);

		$goal->usable = false; //canContinueToUse() defaults to canUse()
		$selector->tick($mob);
		self::assertFalse($goal->running);
		self::assertSame(1, $goal->stopCount);
	}

	public function testRunningGoalIsNotRestartedEveryTick() : void{
		$mob = new FakeMobContext();
		$selector = new GoalSelector();
		$goal = new FakeGoal(GoalFlag::MOVE);
		$selector->add(1, $goal);

		$selector->tick($mob);
		$selector->tick($mob);
		$selector->tick($mob);

		self::assertSame(1, $goal->startCount);
		self::assertSame(3, $goal->tickCount);
	}
}
