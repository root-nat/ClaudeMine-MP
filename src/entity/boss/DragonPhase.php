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

namespace pocketmine\entity\boss;

/**
 * The phases of the ender dragon fight, mirroring vanilla. The dragon is only fully vulnerable while perched
 * (the SITTING_* phases).
 */
enum DragonPhase{
	case CIRCLING;          //holding pattern around the pillars
	case STRAFING;          //firing dragon breath at a player from the air
	case APPROACH_PERCH;    //flying toward the central portal to land
	case PERCHED;           //sitting on the portal, vulnerable to melee
	case TAKEOFF;           //leaving the perch
	case CHARGING;          //charging a player
	case DYING;             //death animation

	/**
	 * Whether the dragon can be melee-hit for full damage in this phase.
	 */
	public function isPerched() : bool{
		return $this === self::PERCHED;
	}
}
