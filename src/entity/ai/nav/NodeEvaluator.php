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

/**
 * Expands the walkable neighbours of a grid node for a standard 1x2 ground mob: 8-way horizontal movement with optional
 * 1-block step-up and multi-block drops, head clearance checks, and no diagonal corner-cutting. Pure logic over a
 * {@link NodeAccess}.
 */
final class NodeEvaluator{

	public function __construct(
		private int $maxStepUp = 1,
		private int $maxDrop = 3
	){}

	/**
	 * Returns true if a 1x2 mob can stand with its feet at (x, y, z): two-high clear body space and a supporting floor.
	 */
	public function canStandAt(NodeAccess $world, int $x, int $y, int $z) : bool{
		if($y <= $world->getMinY() || $y >= $world->getMaxY()){
			return false;
		}
		return $world->isStandable($x, $y, $z)
			&& $world->isPassable($x, $y, $z)
			&& $world->isPassable($x, $y + 1, $z);
	}

	private function isColumnClear(NodeAccess $world, int $x, int $y, int $z) : bool{
		return $world->isPassable($x, $y, $z) && $world->isPassable($x, $y + 1, $z);
	}

	/**
	 * Returns the reachable neighbours of (x, y, z) as a list of [nx, ny, nz, stepCost].
	 *
	 * @return array<int, array{int, int, int, float}>
	 */
	public function getNeighbours(NodeAccess $world, int $x, int $y, int $z) : array{
		$result = [];

		static $directions = [
			[1, 0], [-1, 0], [0, 1], [0, -1], //orthogonal
			[1, 1], [1, -1], [-1, 1], [-1, -1] //diagonal
		];

		foreach($directions as [$dx, $dz]){
			$diagonal = $dx !== 0 && $dz !== 0;
			if($diagonal){
				//forbid cutting across a solid block corner: both orthogonal cells the mob brushes past must be clear
				if(!$this->isColumnClear($world, $x + $dx, $y, $z) || !$this->isColumnClear($world, $x, $y, $z + $dz)){
					continue;
				}
			}

			$baseCost = $diagonal ? Heuristic::DIAGONAL_COST : Heuristic::ORTHOGONAL_COST;
			$neighbour = $this->resolveVerticalNeighbour($world, $x, $y, $z, $dx, $dz, $baseCost);
			if($neighbour !== null){
				$result[] = $neighbour;
			}
		}

		return $result;
	}

	/**
	 * Resolves the destination cell in a horizontal direction, allowing flat moves, one step up, or a drop, in that
	 * priority order.
	 *
	 * @return array{int, int, int, float}|null
	 */
	private function resolveVerticalNeighbour(NodeAccess $world, int $x, int $y, int $z, int $dx, int $dz, float $baseCost) : ?array{
		$nx = $x + $dx;
		$nz = $z + $dz;

		//flat move
		if($this->canStandAt($world, $nx, $y, $nz)){
			return [$nx, $y, $nz, $baseCost + $this->hazardPenalty($world, $nx, $y, $nz)];
		}

		//step up (requires clearance above the mob's current head)
		for($up = 1; $up <= $this->maxStepUp; ++$up){
			$ty = $y + $up;
			if(!$world->isPassable($x, $y + 1 + $up, $z)){ //headroom to rise
				break;
			}
			if($this->canStandAt($world, $nx, $ty, $nz)){
				return [$nx, $ty, $nz, $baseCost + ($up * 0.5) + $this->hazardPenalty($world, $nx, $ty, $nz)];
			}
			if(!$world->isPassable($nx, $ty, $nz)){
				break;
			}
		}

		//drop down
		for($down = 1; $down <= $this->maxDrop; ++$down){
			$ty = $y - $down;
			if(!$world->isPassable($nx, $y - $down + 1, $nz)){ //the column we fall through must be clear
				break;
			}
			if($this->canStandAt($world, $nx, $ty, $nz)){
				return [$nx, $ty, $nz, $baseCost + ($down * 0.25) + $this->hazardPenalty($world, $nx, $ty, $nz)];
			}
		}

		return null;
	}

	private function hazardPenalty(NodeAccess $world, int $x, int $y, int $z) : float{
		return $world->isHazard($x, $y, $z) ? 8.0 : 0.0;
	}
}
