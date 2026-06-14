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
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\math\AxisAlignedBB;
use pocketmine\player\Player;

/**
 * Records the nearest living instance of a feared entity class within range into AVOID_TARGET, so a {@link
 * \pocketmine\entity\ai\goal\AvoidEntityGoal} can flee it (e.g. a creeper fearing a cat, or an ocelot fearing players).
 * An optional ignore predicate exempts specific instances (e.g. a player currently holding the mob's food). The target
 * is re-snapshotted with its current position each scan and cleared once nothing feared is in range.
 */
final class AvoidEntitySensor implements Sensor{

	/**
	 * @param class-string         $avoidClass entities of this class (or a subclass) are feared
	 * @param (\Closure(Entity) : bool)|null $ignore  feared entities for which this returns true are not feared
	 */
	public function __construct(
		private string $avoidClass,
		private float $range = 6.0,
		private int $scanInterval = 10,
		private ?\Closure $ignore = null
	){}

	public function getScanIntervalTicks() : int{
		return $this->scanInterval;
	}

	public function sense(Living $mob, Memory $memory) : void{
		$pos = $mob->getPosition();
		$box = new AxisAlignedBB(
			$pos->x - $this->range, $pos->y - $this->range, $pos->z - $this->range,
			$pos->x + $this->range, $pos->y + $this->range, $pos->z + $this->range
		);

		$best = null;
		$bestDistanceSq = $this->range ** 2;
		foreach($mob->getWorld()->getNearbyEntities($box, $mob) as $entity){
			if(!($entity instanceof $this->avoidClass) || !$entity->isAlive()){
				continue;
			}
			if($entity instanceof Player && !$entity->isSurvival()){
				//spectator/creative players don't affect mob AI, matching NearestPlayersSensor/TemptingPlayerSensor
				continue;
			}
			if($this->ignore !== null && ($this->ignore)($entity)){
				continue;
			}
			$distanceSq = $entity->getPosition()->distanceSquared($pos);
			if($distanceSq <= $bestDistanceSq){
				$bestDistanceSq = $distanceSq;
				$best = $entity;
			}
		}

		if($best === null){
			$memory->erase(MemoryModuleType::AVOID_TARGET);
			return;
		}

		$targetPos = $best->getPosition();
		$memory->set(
			MemoryModuleType::AVOID_TARGET,
			new TargetCandidate($best->getId(), $targetPos->x, $targetPos->y, $targetPos->z, true, $best instanceof Player),
			$this->scanInterval + 1
		);
	}
}
