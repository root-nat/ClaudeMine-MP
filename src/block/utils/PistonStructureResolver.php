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

use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use function array_shift;
use function count;

/**
 * Pure-logic resolver that computes which blocks a piston will move when it extends. The block reactions are supplied
 * through a callback so this can be tested without a live World and reused for both push and pull.
 */
final class PistonStructureResolver{
	public const PUSH_LIMIT = 12;

	public const REACTION_AIR = 0;
	public const REACTION_NORMAL = 1;
	public const REACTION_STICKY = 2;
	public const REACTION_DESTROY = 3;
	public const REACTION_BLOCK = 4;

	private function __construct(){
	}

	/**
	 * Computes the set of blocks moved by a piston.
	 *
	 * @param Vector3  $origin     the first cell in front of the piston (pistonPos + facing)
	 * @param Vector3  $pistonPos  the piston base position, which is never collected even by sticky adhesion
	 * @param int      $facing     the direction blocks are pushed in
	 * @param \Closure $reactionAt resolves the movement reaction of the block at a position
	 * @phpstan-param \Closure(Vector3) : self::REACTION_* $reactionAt
	 *
	 * @return array{Vector3[], Vector3[]}|null [blocks to move (near-to-far order), blocks to destroy], or null if the
	 *                                          piston cannot move (immovable block or push limit exceeded)
	 */
	public static function computeMovement(Vector3 $origin, Vector3 $pistonPos, int $facing, \Closure $reactionAt) : ?array{
		/** @var Vector3[] $move */
		$move = [];
		/** @var Vector3[] $destroy */
		$destroy = [];
		$seen = [];

		$queue = [$origin];
		while(count($queue) > 0){
			$pos = array_shift($queue);
			$key = $pos->getFloorX() . ":" . $pos->getFloorY() . ":" . $pos->getFloorZ();
			if(isset($seen[$key])){
				continue;
			}

			$reaction = $reactionAt($pos);
			if($reaction === self::REACTION_AIR){
				continue;
			}
			if($reaction === self::REACTION_BLOCK){
				return null;
			}

			$seen[$key] = true;
			if($reaction === self::REACTION_DESTROY){
				$destroy[] = $pos;
				continue;
			}

			$move[] = $pos;
			if(count($move) > self::PUSH_LIMIT){
				return null;
			}

			//the cell this block moves into must also be vacated
			$queue[] = $pos->getSide($facing);

			if($reaction === self::REACTION_STICKY){
				foreach(Facing::ALL as $face){
					$neighbour = $pos->getSide($face);
					if($neighbour->equals($pistonPos)){
						continue;
					}
					$queue[] = $neighbour;
				}
			}
		}

		return [$move, $destroy];
	}
}
