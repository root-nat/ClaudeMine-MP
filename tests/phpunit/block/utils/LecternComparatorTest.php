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

class LecternComparatorTest extends TestCase{

	public function testNoBookGivesNoSignal() : void{
		self::assertSame(0, LecternComparator::signalStrength(0, 0));
	}

	public function testSinglePageBookIsFullSignal() : void{
		self::assertSame(15, LecternComparator::signalStrength(0, 1));
	}

	public function testFirstPageIsOne() : void{
		self::assertSame(1, LecternComparator::signalStrength(0, 10));
	}

	public function testLastPageIsFifteen() : void{
		self::assertSame(15, LecternComparator::signalStrength(9, 10));
	}

	public function testSignalRisesMonotonicallyAcrossPages() : void{
		$previous = -1;
		for($page = 0; $page < 15; ++$page){
			$signal = LecternComparator::signalStrength($page, 15);
			self::assertGreaterThanOrEqual($previous, $signal);
			self::assertGreaterThanOrEqual(1, $signal);
			self::assertLessThanOrEqual(15, $signal);
			$previous = $signal;
		}
	}
}
