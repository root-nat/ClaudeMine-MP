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

namespace pocketmine\player;

use function cos;
use function deg2rad;
use function sin;

/**
 * Pure geometry for shield blocking: a raised shield only stops attacks coming from the front 180° arc the defender is
 * facing. Kept free of entity/world coupling so it can be unit-tested. {@link Player} resolves the live yaw/positions.
 */
final class ShieldBlockingResolver{

	private function __construct(){
		//NOOP
	}

	/**
	 * Whether a defender facing $defenderYaw at (defenderX, defenderZ) blocks an attack originating at (attackerX,
	 * attackerZ) - i.e. the source is within the front hemisphere the shield covers.
	 */
	public static function blocksAttackFrom(float $defenderYaw, float $defenderX, float $defenderZ, float $attackerX, float $attackerZ) : bool{
		$toAttackerX = $attackerX - $defenderX;
		$toAttackerZ = $attackerZ - $defenderZ;
		if($toAttackerX === 0.0 && $toAttackerZ === 0.0){
			return true;
		}
		//PMMP facing vector for a yaw (yaw 0 = +Z/south): (-sin, cos)
		$facingX = -sin(deg2rad($defenderYaw));
		$facingZ = cos(deg2rad($defenderYaw));
		//positive dot product => attacker is in front of the shield
		return ($toAttackerX * $facingX) + ($toAttackerZ * $facingZ) > 0;
	}
}
