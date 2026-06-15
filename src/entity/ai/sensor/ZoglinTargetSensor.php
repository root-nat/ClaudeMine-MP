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
use pocketmine\entity\Creeper;
use pocketmine\entity\Living;
use pocketmine\entity\Zoglin;
use pocketmine\math\AxisAlignedBB;
use pocketmine\player\Player;

/**
 * Records every living thing in range - survival players, animals, villagers, other monsters - as a {@link
 * TargetCandidate} into NEAREST_ENTITIES, except fellow zoglins and creepers (which a zoglin leaves alone, like vanilla).
 * Drives the zoglin's indiscriminate aggression. The only world access is World::getNearbyEntities.
 */
final class ZoglinTargetSensor implements Sensor{

	public function __construct(
		private float $range,
		private int $scanInterval = 10
	){}

	public function getScanIntervalTicks() : int{
		return $this->scanInterval;
	}

	public function sense(Living $owner, Memory $memory) : void{
		$pos = $owner->getPosition();
		$bb = new AxisAlignedBB(
			$pos->x - $this->range, $pos->y - $this->range, $pos->z - $this->range,
			$pos->x + $this->range, $pos->y + $this->range, $pos->z + $this->range
		);

		$candidates = [];
		foreach($owner->getWorld()->getNearbyEntities($bb, $owner) as $entity){
			if(!$entity instanceof Living || !$entity->isAlive()){
				continue;
			}
			if($entity instanceof Zoglin || $entity instanceof Creeper){
				continue; //a zoglin ignores its own kind and creepers
			}
			if($entity instanceof Player && !$entity->isSurvival()){
				continue; //leave creative/spectator players alone
			}
			$ePos = $entity->getPosition();
			$candidates[] = new TargetCandidate($entity->getId(), $ePos->x, $ePos->y, $ePos->z, true, $entity instanceof Player);
		}

		$memory->set(MemoryModuleType::NEAREST_ENTITIES, $candidates);
	}
}
