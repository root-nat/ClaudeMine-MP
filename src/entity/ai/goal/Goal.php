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

namespace pocketmine\entity\ai\goal;

use pocketmine\entity\ai\MobContext;

/**
 * A unit of mob behaviour, arbitrated by {@link GoalSelector}. Goals declare which action flags they occupy so the
 * selector can run compatible goals concurrently and preempt lower-priority ones.
 */
interface Goal{

	/**
	 * Returns whether this goal wants to start right now.
	 */
	public function canUse(MobContext $mob) : bool;

	/**
	 * Returns whether a running goal should keep running. Defaults to canUse() for most goals.
	 */
	public function canContinueToUse(MobContext $mob) : bool;

	/**
	 * Bitmask of {@link GoalFlag} action slots this goal occupies while running.
	 */
	public function getFlags() : int;

	public function start(MobContext $mob) : void;

	public function stop(MobContext $mob) : void;

	public function tick(MobContext $mob) : void;
}
