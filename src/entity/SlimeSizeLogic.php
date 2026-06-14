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

use function intdiv;
use function max;

/**
 * Pure size mechanics shared by slimes and magma cubes: a slime's size (1 small, 2 medium, 4 large) drives its health,
 * touch damage, XP, hitbox scale and how it splits into smaller slimes on death. Kept free of entity/world coupling so
 * the formulas are unit-testable.
 */
final class SlimeSizeLogic{
	public const SIZE_SMALL = 1;
	public const SIZE_MEDIUM = 2;
	public const SIZE_LARGE = 4;

	private const BASE_EDGE = 0.51;

	private function __construct(){
	}

	public static function clampSize(int $size) : int{
		return match(true){
			$size >= self::SIZE_LARGE => self::SIZE_LARGE,
			$size >= self::SIZE_MEDIUM => self::SIZE_MEDIUM,
			default => self::SIZE_SMALL,
		};
	}

	public static function healthFor(int $size) : int{
		return $size * $size;
	}

	/**
	 * Slime touch damage: tiny slimes are harmless, larger ones hit for their size.
	 */
	public static function slimeDamage(int $size) : int{
		return $size <= self::SIZE_SMALL ? 0 : $size;
	}

	/**
	 * Magma cubes hit harder than slimes of the same size.
	 */
	public static function magmaCubeDamage(int $size) : int{
		return $size <= self::SIZE_SMALL ? 0 : $size + 2;
	}

	public static function xpFor(int $size) : int{
		return $size;
	}

	public static function hitboxEdge(int $size) : float{
		return self::BASE_EDGE * $size;
	}

	public static function canSplit(int $size) : bool{
		return $size > self::SIZE_SMALL;
	}

	/**
	 * Size of the children produced when a slime of this size dies (half, floored to the next valid size).
	 */
	public static function childSize(int $size) : int{
		return max(self::SIZE_SMALL, intdiv($size, 2));
	}

	/**
	 * Number of children a dying slime splits into (vanilla 2-4); takes the random roll so it stays deterministic/pure.
	 */
	public static function splitCount(int $randomRoll) : int{
		return 2 + ($randomRoll % 3); //2, 3 or 4
	}

	/**
	 * Base ticks between idle hops; larger slimes are heavier and hop a little less often (a random jitter is added live).
	 */
	public static function hopIntervalTicks(int $size) : int{
		return match(self::clampSize($size)){
			self::SIZE_SMALL => 10,
			self::SIZE_MEDIUM => 13,
			default => 16,
		};
	}

	/**
	 * Horizontal velocity (blocks/tick) added on each hop; larger slimes cover more ground per leap.
	 */
	public static function hopHorizontalSpeed(int $size) : float{
		return match(self::clampSize($size)){
			self::SIZE_SMALL => 0.24,
			self::SIZE_MEDIUM => 0.30,
			default => 0.36,
		};
	}
}
