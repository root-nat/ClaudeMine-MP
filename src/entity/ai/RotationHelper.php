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

use function atan2;
use function fmod;
use const M_PI;

/**
 * Pure yaw maths for mob orientation, isolated from the live Entity so it can be unit-tested. A walking mob must rotate
 * to face the direction it is moving; this computes the heading yaw from a movement vector (matching the convention of
 * {@link \pocketmine\entity\Living::lookAt}) and turns the current yaw towards it at a capped rate for a natural turn.
 */
final class RotationHelper{

	private function __construct(){
		//NOOP
	}

	/**
	 * Normalises any yaw to the [0, 360) range.
	 */
	public static function normalizeYaw(float $yaw) : float{
		$yaw = fmod($yaw, 360.0);
		if($yaw < 0){
			$yaw += 360.0;
		}
		return $yaw;
	}

	/**
	 * The Minecraft yaw that faces a horizontal movement vector (yaw 0 = +Z/south, 90 = -X/west, 270 = +X/east).
	 * Matches Living::lookAt so movement orientation and look orientation agree.
	 */
	public static function directionToYaw(float $dx, float $dz) : float{
		return self::normalizeYaw(atan2($dz, $dx) / M_PI * 180.0 - 90.0);
	}

	/**
	 * The shortest signed angular distance from $from to $to, in the range (-180, 180].
	 */
	public static function angleDifference(float $from, float $to) : float{
		$diff = self::normalizeYaw($to - $from);
		if($diff > 180.0){
			$diff -= 360.0;
		}
		return $diff;
	}

	/**
	 * Rotates $current towards $target along the shortest arc, moving at most $maxStep degrees. Returns the new
	 * normalised yaw; once within $maxStep it snaps exactly onto $target.
	 */
	public static function turnTowards(float $current, float $target, float $maxStep) : float{
		$diff = self::angleDifference($current, $target);
		if($diff <= $maxStep && $diff >= -$maxStep){
			return self::normalizeYaw($target);
		}
		return self::normalizeYaw($current + ($diff > 0 ? $maxStep : -$maxStep));
	}
}
