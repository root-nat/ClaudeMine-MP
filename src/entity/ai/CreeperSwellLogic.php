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

namespace pocketmine\entity\ai;

use function max;
use function min;

/**
 * Pure state machine for creeper fuse swelling. The fuse counts up while a target is within ignite range and counts
 * back down otherwise; the creeper detonates when the fuse reaches its maximum. Kept separate from the entity so the
 * thresholds are unit-testable.
 */
final class CreeperSwellLogic{
	public const MAX_FUSE = 30;
	public const IGNITE_RANGE = 3.0;

	private function __construct(){
	}

	/**
	 * Advances the fuse one tick given the distance to the current target (or null if there is no target).
	 */
	public static function updateFuse(int $fuse, ?float $distanceToTarget) : int{
		$swelling = $distanceToTarget !== null && $distanceToTarget <= self::IGNITE_RANGE;
		$next = $fuse + ($swelling ? 1 : -1);
		return max(0, min(self::MAX_FUSE, $next));
	}

	public static function shouldExplode(int $fuse) : bool{
		return $fuse >= self::MAX_FUSE;
	}

	/**
	 * Returns +1 while swelling, -1 while deflating, 0 when unchanged, for the client swell-direction metadata.
	 */
	public static function swellDirection(int $oldFuse, int $newFuse) : int{
		return $newFuse <=> $oldFuse;
	}
}
