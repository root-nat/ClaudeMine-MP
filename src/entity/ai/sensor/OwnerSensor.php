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
use pocketmine\entity\ai\memory\MemoryModuleType;
use pocketmine\entity\ai\target\TargetCandidate;
use pocketmine\entity\Living;
use pocketmine\entity\TameableAnimal;

/**
 * Records a tamed mob's owner in the OWNER memory slot when the owner is online, in the same world and far enough away to
 * be worth walking to. Cleared while the mob is untamed, sitting or its owner is unreachable, so the follow goal idles.
 */
final class OwnerSensor implements Sensor{

	/** Don't bother following while already within this many blocks of the owner. */
	private const FOLLOW_FROM_DISTANCE_SQ = 9.0;

	public function __construct(
		private int $scanInterval = 10
	){}

	public function getScanIntervalTicks() : int{
		return $this->scanInterval;
	}

	public function sense(Living $owner, Memory $memory) : void{
		if(!$owner instanceof TameableAnimal || !$owner->isTamed() || $owner->isSitting()){
			$memory->erase(MemoryModuleType::OWNER);
			return;
		}

		$player = $owner->findOwnerPlayer();
		if($player === null || $player->getWorld() !== $owner->getWorld() || !$player->isAlive()){
			$memory->erase(MemoryModuleType::OWNER);
			return;
		}

		$pos = $player->getPosition();
		if($pos->distanceSquared($owner->getPosition()) < self::FOLLOW_FROM_DISTANCE_SQ){
			$memory->erase(MemoryModuleType::OWNER);
			return;
		}

		$memory->set(MemoryModuleType::OWNER, new TargetCandidate($player->getId(), $pos->x, $pos->y, $pos->z, true, true));
	}
}
