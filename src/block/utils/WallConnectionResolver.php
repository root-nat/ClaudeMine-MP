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

/**
 * Pure decision logic for vanilla wall connection heights and the centre post, isolated from the live World so it can be
 * unit-tested exhaustively. {@link \pocketmine\block\Wall} resolves the world-dependent inputs and delegates here.
 *
 * Rules (Java 1.16+ / Bedrock parity):
 *  - A connected side is TALL when the block above the wall covers its top (e.g. a full solid block), otherwise SHORT.
 *  - The centre post is omitted only for a straight pass-through (exactly two opposite connections) with nothing above;
 *    any block above (which would otherwise leave a gap over the centre) forces the post, as do all non-straight shapes.
 *
 * @phpstan-type WallConnectionSet array<Facing::NORTH|Facing::EAST|Facing::SOUTH|Facing::WEST, WallConnectionType>
 */
final class WallConnectionResolver{

	private function __construct(){
		//NOOP
	}

	/**
	 * @param array<int, bool> $connected      horizontal Facing => whether a connectable block sits on that side
	 * @param bool             $aboveCoversTop  whether the block above covers the wall top (raises connections to TALL)
	 * @param bool             $aboveForcesPost whether the block above forces a centre post (a full solid block, or a wall
	 *                                          that itself has a post). A plain wall above does NOT force a post, so a
	 *                                          straight stacked wall stays a flat panel with posts only at its ends.
	 *
	 * @return array{array<int, WallConnectionType>, bool} the connection set keyed by facing, and whether a post is present
	 * @phpstan-return array{WallConnectionSet, bool}
	 */
	public static function resolve(array $connected, bool $aboveCoversTop, bool $aboveForcesPost) : array{
		$type = $aboveCoversTop ? WallConnectionType::TALL : WallConnectionType::SHORT;

		$connections = [];
		foreach(Facing::HORIZONTAL as $facing){
			if($connected[$facing] ?? false){
				$connections[$facing] = $type;
			}
		}

		$north = isset($connections[Facing::NORTH]);
		$south = isset($connections[Facing::SOUTH]);
		$west = isset($connections[Facing::WEST]);
		$east = isset($connections[Facing::EAST]);

		$straightThrough =
			($north && $south && !$west && !$east) ||
			($west && $east && !$north && !$south);

		$post = !$straightThrough || $aboveForcesPost;

		return [$connections, $post];
	}
}
