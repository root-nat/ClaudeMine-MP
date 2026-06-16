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

namespace pocketmine\entity\ai\nav;

use PHPUnit\Framework\TestCase;
use function shuffle;

class BinaryHeapTest extends TestCase{

	public function testExtractsInAscendingFOrder() : void{
		$heap = new BinaryHeap();
		$values = [5.0, 1.0, 9.0, 3.0, 7.0, 2.0, 8.0, 4.0, 6.0, 0.0];
		shuffle($values);
		foreach($values as $i => $f){
			$node = new PathPoint($i, 0, 0);
			$node->f = $f;
			$heap->insert($node);
		}

		$previous = -1.0;
		$count = 0;
		while(!$heap->isEmpty()){
			$min = $heap->extractMin();
			self::assertGreaterThanOrEqual($previous, $min->f);
			$previous = $min->f;
			++$count;
		}
		self::assertSame(10, $count);
	}

	public function testDecreaseKeyReorders() : void{
		$heap = new BinaryHeap();
		$a = new PathPoint(0, 0, 0);
		$a->f = 10.0;
		$b = new PathPoint(1, 0, 0);
		$b->f = 5.0;
		$heap->insert($a);
		$heap->insert($b);

		$a->f = 1.0;
		$heap->decreaseKey($a);

		self::assertSame($a, $heap->extractMin());
		self::assertSame($b, $heap->extractMin());
	}

	public function testEmptyHeapThrows() : void{
		$this->expectException(\UnderflowException::class);
		(new BinaryHeap())->extractMin();
	}

	public function testSizeTracking() : void{
		$heap = new BinaryHeap();
		self::assertTrue($heap->isEmpty());
		$heap->insert(new PathPoint(0, 0, 0));
		$heap->insert(new PathPoint(1, 0, 0));
		self::assertSame(2, $heap->size());
		$heap->extractMin();
		self::assertSame(1, $heap->size());
	}
}
