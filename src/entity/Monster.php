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

use pocketmine\entity\ai\AbstractMob;
use pocketmine\entity\ai\goal\RandomStrollGoal;
use pocketmine\entity\ai\sensor\HurtBySensor;
use pocketmine\entity\ai\sensor\NearestPlayersSensor;

/**
 * Base class for hostile monsters. Wires the player-targeting and retaliation sensors plus idle wandering; species
 * declare their own attack goal(s) in registerAttackGoals(). Built on the {@link AbstractMob} AI core.
 */
abstract class Monster extends AbstractMob{

	protected function registerBehaviour() : void{
		$this->addSensor(new NearestPlayersSensor($this->getFollowRange()));
		$this->addSensor(new HurtBySensor());

		$this->registerAttackGoals();
		$this->addGoal(8, new RandomStrollGoal());
	}

	/**
	 * Declares the monster's combat goal(s) (melee, ranged, swell...). Called before idle goals so they take priority.
	 */
	abstract protected function registerAttackGoals() : void;
}
