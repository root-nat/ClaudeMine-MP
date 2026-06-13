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

use function count;

/**
 * Minimal binary min-heap of {@link PathPoint}s ordered by their f score, with decrease-key support via each node's
 * stored heap index. Used as the A* open set. Pure data structure, fully unit-testable.
 */
final class BinaryHeap{

	/** @var PathPoint[] */
	private array $nodes = [];

	public function isEmpty() : bool{
		return count($this->nodes) === 0;
	}

	public function size() : int{
		return count($this->nodes);
	}

	public function insert(PathPoint $node) : void{
		$node->heapIndex = count($this->nodes);
		$this->nodes[] = $node;
		$this->siftUp($node->heapIndex);
	}

	public function extractMin() : PathPoint{
		if(count($this->nodes) === 0){
			throw new \UnderflowException("Heap is empty");
		}
		$min = $this->nodes[0];
		$last = array_pop($this->nodes);
		$min->heapIndex = -1;
		if(count($this->nodes) > 0 && $last !== $min){
			$this->nodes[0] = $last;
			$last->heapIndex = 0;
			$this->siftDown(0);
		}
		return $min;
	}

	/**
	 * Notifies the heap that the given node's f score decreased and it must move up to restore the heap property.
	 */
	public function decreaseKey(PathPoint $node) : void{
		if($node->heapIndex >= 0){
			$this->siftUp($node->heapIndex);
		}
	}

	private function siftUp(int $index) : void{
		$node = $this->nodes[$index];
		while($index > 0){
			$parentIndex = ($index - 1) >> 1;
			$parent = $this->nodes[$parentIndex];
			if($node->f >= $parent->f){
				break;
			}
			$this->nodes[$index] = $parent;
			$parent->heapIndex = $index;
			$index = $parentIndex;
		}
		$this->nodes[$index] = $node;
		$node->heapIndex = $index;
	}

	private function siftDown(int $index) : void{
		$count = count($this->nodes);
		$node = $this->nodes[$index];
		while(true){
			$left = ($index << 1) + 1;
			$right = $left + 1;
			$smallest = $index;
			$smallestNode = $node;
			if($left < $count && $this->nodes[$left]->f < $smallestNode->f){
				$smallest = $left;
				$smallestNode = $this->nodes[$left];
			}
			if($right < $count && $this->nodes[$right]->f < $smallestNode->f){
				$smallest = $right;
				$smallestNode = $this->nodes[$right];
			}
			if($smallest === $index){
				break;
			}
			$this->nodes[$index] = $this->nodes[$smallest];
			$this->nodes[$index]->heapIndex = $index;
			$index = $smallest;
		}
		$this->nodes[$index] = $node;
		$node->heapIndex = $index;
	}
}
