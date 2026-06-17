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

namespace pocketmine\world\generator\structure\jigsaw;

use pocketmine\math\Facing;
use pocketmine\utils\Random;
use function array_shift;
use function count;

/**
 * The deterministic, bounded jigsaw assembler. From a start piece it breadth-first expands open connectors: for each, it
 * draws a piece from the connector's target pool, finds the rotation whose mating connector faces the opposite way AND
 * targets the same pool (so a corridor mates end-to-end, not via a side branch), positions it adjacent, and accepts it
 * only if it fits the overall bounds and overlaps no already-placed piece (AABB).
 *
 * It is a PURE function of (start, pools, Random, maxPieces, bounds) - the only entropy is the per-connector pool draw,
 * in a fixed BFS order - so the SAME seed yields the SAME piece layout. That is what lets each chunk re-derive the whole
 * structure identically and write only its own clipped slice, with no persisted structure-start cache required.
 */
final class JigsawAssembler{

	private function __construct(){
		//NOOP
	}

	/**
	 * @return PlacedPiece[] the placed pieces (always at least the start piece if it fits the bounds, else empty)
	 */
	public static function assemble(StructureTemplate $start, JigsawPools $pools, Random $random, int $maxPieces, StructureBoundingBox $bounds) : array{
		$startPiece = new PlacedPiece($start, 0, 0, 0, 0);
		if(!$bounds->containsBox($startPiece->bounds())){
			return [];
		}
		$placed = [$startPiece];
		$frontier = $startPiece->worldConnectors();

		while(count($frontier) > 0 && count($placed) < $maxPieces){
			$conn = array_shift($frontier);
			$pool = $pools->get($conn->pool);
			if($pool === null || $pool->isEmpty()){
				continue;
			}
			$candidate = $pool->pick($random); //the only Random draw, one per expanded connector (fixed BFS order)
			if($candidate === null){
				continue;
			}

			$needFacing = Facing::opposite($conn->facing);
			[$ox, $oy, $oz] = self::offset($conn->facing);
			$targetX = $conn->x + $ox;
			$targetY = $conn->y + $oy;
			$targetZ = $conn->z + $oz;

			$attached = false;
			for($rotation = 0; $rotation < 4 && !$attached; ++$rotation){
				$rotated = $candidate->rotated($rotation);
				foreach($rotated->getConnectors() as $cc){
					if($cc->facing !== $needFacing || $cc->pool !== $conn->pool){
						continue; //must face the opposite way AND be the same connection type (pool) to mate
					}
					//position the candidate so its mating connector cell lands at the target cell
					$piece = new PlacedPiece($rotated, $targetX - $cc->x, $targetY - $cc->y, $targetZ - $cc->z, $rotation);
					$box = $piece->bounds();
					if(!$bounds->containsBox($box) || self::overlapsAny($box, $placed)){
						continue;
					}
					$placed[] = $piece;
					foreach($piece->worldConnectors() as $wc){
						if($wc->x === $targetX && $wc->y === $targetY && $wc->z === $targetZ && $wc->facing === $needFacing){
							continue; //the connector we just mated - don't expand it again
						}
						$frontier[] = $wc;
					}
					$attached = true;
					break;
				}
			}
			//an unattached connector is simply left as a dead end
		}
		return $placed;
	}

	/**
	 * @param PlacedPiece[] $placed
	 */
	private static function overlapsAny(StructureBoundingBox $box, array $placed) : bool{
		foreach($placed as $piece){
			if($box->overlaps($piece->bounds())){
				return true;
			}
		}
		return false;
	}

	/**
	 * @return array{int, int, int} the unit block offset for a facing
	 */
	private static function offset(int $facing) : array{
		return match($facing){
			Facing::NORTH => [0, 0, -1],
			Facing::SOUTH => [0, 0, 1],
			Facing::EAST => [1, 0, 0],
			Facing::WEST => [-1, 0, 0],
			Facing::UP => [0, 1, 0],
			Facing::DOWN => [0, -1, 0],
			default => [0, 0, 0]
		};
	}
}
