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

namespace pocketmine\entity\ai\sensor;

use pocketmine\entity\ai\memory\Memory;
use pocketmine\entity\Living;

/**
 * Reads the live world around a mob and records pure {@link \pocketmine\entity\ai\target\TargetCandidate} snapshots into
 * its {@link Memory}. Sensors are the only world-reading half of the AI; everything downstream operates on the recorded
 * value objects. Scans are throttled by getScanIntervalTicks().
 */
interface Sensor{

	/**
	 * Minimum number of ticks between scans. The mob runner skips sensing on other ticks to bound cost.
	 */
	public function getScanIntervalTicks() : int;

	public function sense(Living $owner, Memory $memory) : void;
}
