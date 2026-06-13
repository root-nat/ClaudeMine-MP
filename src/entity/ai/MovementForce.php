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

/**
 * Converts a desired steady-state horizontal speed into the per-tick force a mob must add via addMotion(), accounting
 * for the friction the engine applies each tick (motion *= friction). Pure maths, unit-testable.
 *
 * The engine integrates horizontal motion as v_{n+1} = (v_n + F) * friction. The fixed point of that recurrence is
 * v* = F * friction / (1 - friction), so to hold a target speed v* we must apply F = v* * (1 - friction) / friction.
 */
final class MovementForce{

	/**
	 * Effective per-tick horizontal friction for a Living mob walking on a default-friction block. Tuned to approximate
	 * Bedrock walk speed; exact parity requires in-game calibration.
	 */
	public const DEFAULT_GROUND_FRICTION = 0.546;

	private function __construct(){
	}

	public static function computeForce(float $targetSpeed, float $friction = self::DEFAULT_GROUND_FRICTION) : float{
		if($friction <= 0.0 || $friction >= 1.0){
			return $targetSpeed;
		}
		return $targetSpeed * (1.0 - $friction) / $friction;
	}

	/**
	 * Simulates the steady-state speed reached after applying $force each tick under $friction, used to verify the
	 * inverse relationship in tests.
	 */
	public static function steadyStateSpeed(float $force, float $friction = self::DEFAULT_GROUND_FRICTION) : float{
		if($friction <= 0.0 || $friction >= 1.0){
			return $force;
		}
		return $force * $friction / (1.0 - $friction);
	}
}
