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

use function array_reverse;

/**
 * A* search over a {@link NodeAccess} grid using a {@link NodeEvaluator} for neighbour expansion and an admissible
 * octile heuristic. Supports partial paths (walk as close as possible) like Bedrock mobs. Fully pure and deterministic.
 */
final class AStarPathFinder{

	public function __construct(
		private NodeEvaluator $evaluator = new NodeEvaluator(),
		private int $maxVisitedNodes = 512
	){}

	/**
	 * Finds a path from the start cell to the target cell.
	 *
	 * @param bool $allowPartial if true, when the exact target is unreachable within the node budget, returns a path to
	 *                           the visited node closest to the target (still making progress); if false, returns null.
	 */
	public function findPath(NodeAccess $world, int $startX, int $startY, int $startZ, int $targetX, int $targetY, int $targetZ, bool $allowPartial = true) : ?Path{
		if(!$this->evaluator->canStandAt($world, $startX, $startY, $startZ)){
			return null;
		}

		$start = new PathPoint($startX, $startY, $startZ);
		$start->h = Heuristic::octile($startX - $targetX, $startY - $targetY, $startZ - $targetZ);
		$start->f = $start->h;

		/** @var array<string, PathPoint> $allNodes */
		$allNodes = [$start->hash() => $start];
		$open = new BinaryHeap();
		$open->insert($start);

		$best = $start;
		$visited = 0;

		while(!$open->isEmpty() && $visited < $this->maxVisitedNodes){
			$current = $open->extractMin();
			$current->closed = true;
			++$visited;

			if($current->x === $targetX && $current->y === $targetY && $current->z === $targetZ){
				return $this->reconstruct($current);
			}
			if($current->h < $best->h){
				$best = $current;
			}

			foreach($this->evaluator->getNeighbours($world, $current->x, $current->y, $current->z) as [$nx, $ny, $nz, $stepCost]){
				$hash = PathPoint::hashOf($nx, $ny, $nz);
				$neighbour = $allNodes[$hash] ?? null;
				$tentativeG = $current->g + $stepCost;

				if($neighbour === null){
					$neighbour = new PathPoint($nx, $ny, $nz);
					$neighbour->g = $tentativeG;
					$neighbour->h = Heuristic::octile($nx - $targetX, $ny - $targetY, $nz - $targetZ);
					$neighbour->f = $neighbour->g + $neighbour->h;
					$neighbour->parent = $current;
					$allNodes[$hash] = $neighbour;
					$open->insert($neighbour);
				}elseif(!$neighbour->closed && $tentativeG < $neighbour->g){
					$neighbour->g = $tentativeG;
					$neighbour->f = $tentativeG + $neighbour->h;
					$neighbour->parent = $current;
					$open->decreaseKey($neighbour);
				}
			}
		}

		if($allowPartial && $best !== $start){
			return $this->reconstruct($best);
		}
		return null;
	}

	private function reconstruct(PathPoint $end) : Path{
		$points = [];
		$node = $end;
		while($node !== null){
			$points[] = [$node->x, $node->y, $node->z];
			$node = $node->parent;
		}
		return new Path(array_reverse($points));
	}
}
