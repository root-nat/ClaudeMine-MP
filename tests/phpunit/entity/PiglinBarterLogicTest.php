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

namespace pocketmine\entity;

use PHPUnit\Framework\TestCase;

final class PiglinBarterLogicTest extends TestCase{

	public function testTotalWeightSumsTheTable() : void{
		self::assertSame(210, PiglinBarterLogic::totalWeight());
	}

	public function testFirstWeightSlotsSelectTheFirstRow() : void{
		self::assertSame(0, PiglinBarterLogic::selectIndex(0));
		self::assertSame(0, PiglinBarterLogic::selectIndex(39));
	}

	public function testWeightBoundaryAdvancesToTheNextRow() : void{
		self::assertSame(1, PiglinBarterLogic::selectIndex(40));
	}

	public function testLastWeightSlotSelectsTheLastRow() : void{
		self::assertSame(9, PiglinBarterLogic::selectIndex(PiglinBarterLogic::totalWeight() - 1));
	}

	public function testWeightRollWrapsModuloTheTotalWeight() : void{
		self::assertSame(PiglinBarterLogic::selectIndex(0), PiglinBarterLogic::selectIndex(PiglinBarterLogic::totalWeight()));
	}

	public function testAnyRollSelectsAValidRow() : void{
		$total = PiglinBarterLogic::totalWeight();
		for($r = -$total; $r < 2 * $total; ++$r){
			$index = PiglinBarterLogic::selectIndex($r);
			self::assertGreaterThanOrEqual(0, $index);
			self::assertLessThan(10, $index);
		}
	}
}
