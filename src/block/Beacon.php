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

namespace pocketmine\block;

use pocketmine\block\inventory\BeaconInventory;
use pocketmine\block\tile\Beacon as TileBeacon;
use pocketmine\block\utils\BeaconLogic;
use pocketmine\data\bedrock\EffectIdMap;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

final class Beacon extends Transparent{

	private const UPDATE_INTERVAL = 80; //4 seconds, like vanilla

	public function getLightLevel() : int{
		return 15;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($player !== null){
			$player->setCurrentWindow(new BeaconInventory($this->position));
			return true;
		}
		return false;
	}

	public function onPostPlace() : void{
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, self::UPDATE_INTERVAL);
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$tile = $world->getTile($this->position);
		if($tile instanceof TileBeacon){
			$level = $this->calculatePyramidLevel();
			if($level > 0 && $tile->getPrimaryEffect() !== 0 && $this->hasUnobstructedSky()){
				$this->applyEffects($level, $tile);
			}
		}
		$world->scheduleDelayedBlockUpdate($this->position, self::UPDATE_INTERVAL);
	}

	/**
	 * Reads the mineral-block pyramid below the beacon and returns its level (0-4).
	 */
	public function calculatePyramidLevel() : int{
		$world = $this->position->getWorld();
		$baseX = $this->position->getFloorX();
		$baseY = $this->position->getFloorY();
		$baseZ = $this->position->getFloorZ();

		$layersComplete = [];
		for($layer = 1; $layer <= BeaconLogic::MAX_LEVEL; $layer++){
			$y = $baseY - $layer;
			$complete = true;
			for($dx = -$layer; $dx <= $layer && $complete; $dx++){
				for($dz = -$layer; $dz <= $layer; $dz++){
					if(!BeaconLogic::isBeaconBaseBlock($world->getBlockAt($baseX + $dx, $y, $baseZ + $dz))){
						$complete = false;
						break;
					}
				}
			}
			$layersComplete[] = $complete;
		}
		return BeaconLogic::pyramidLevel($layersComplete);
	}

	/**
	 * The beam must reach the sky: every block directly above the beacon up to the build limit must be transparent.
	 */
	private function hasUnobstructedSky() : bool{
		$world = $this->position->getWorld();
		$x = $this->position->getFloorX();
		$z = $this->position->getFloorZ();
		$maxY = $world->getMaxY();
		for($y = $this->position->getFloorY() + 1; $y < $maxY; $y++){
			if(!$world->getBlockAt($x, $y, $z)->isTransparent()){
				return false;
			}
		}
		return true;
	}

	private function applyEffects(int $level, TileBeacon $tile) : void{
		$primaryId = $tile->getPrimaryEffect();
		$primaryEffect = EffectIdMap::getInstance()->fromId($primaryId);
		if($primaryEffect === null){
			return;
		}

		$secondaryId = $tile->getSecondaryEffect();
		$primaryAmplifier = 0;
		$secondaryEffect = null;
		if(BeaconLogic::secondaryUpgradesPrimary($level, $primaryId, $secondaryId)){
			$primaryAmplifier = 1; //picking the primary again in the secondary slot upgrades it to amplifier II
		}elseif($level >= BeaconLogic::MAX_LEVEL && $secondaryId !== 0){
			$secondaryEffect = EffectIdMap::getInstance()->fromId($secondaryId);
		}

		$duration = BeaconLogic::effectDurationTicks($level);
		$range = BeaconLogic::effectRange($level);
		$world = $this->position->getWorld();
		$box = new AxisAlignedBB(
			$this->position->getFloorX() - $range, $world->getMinY(), $this->position->getFloorZ() - $range,
			$this->position->getFloorX() + $range + 1, $world->getMaxY(), $this->position->getFloorZ() + $range + 1
		);

		foreach($world->getNearbyEntities($box) as $entity){
			if($entity instanceof Player){
				$entity->getEffects()->add(new EffectInstance($primaryEffect, $duration, $primaryAmplifier, true, true));
				if($secondaryEffect !== null){
					$entity->getEffects()->add(new EffectInstance($secondaryEffect, $duration, 0, true, true));
				}
			}
		}
	}
}
