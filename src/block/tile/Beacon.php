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

namespace pocketmine\block\tile;

use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\EffectIdMap;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\Living;
use pocketmine\math\AxisAlignedBB;
use pocketmine\nbt\tag\CompoundTag;
use function in_array;

final class Beacon extends Spawnable{
	private const TAG_PRIMARY = "primary"; //TAG_Int
	private const TAG_SECONDARY = "secondary"; //TAG_Int

	private int $primaryEffect = 0;
	private int $secondaryEffect = 0;

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$nbt->setInt(self::TAG_PRIMARY, $this->primaryEffect);
		$nbt->setInt(self::TAG_SECONDARY, $this->secondaryEffect);
	}

	public function readSaveData(CompoundTag $nbt) : void{
		//TODO: PC uses Primary and Secondary (capitalized first letter), we don't read them here because the IDs would be different
		$this->primaryEffect = $nbt->getInt(self::TAG_PRIMARY, 0);
		$this->secondaryEffect = $nbt->getInt(self::TAG_SECONDARY, 0);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setInt(self::TAG_PRIMARY, $this->primaryEffect);
		$nbt->setInt(self::TAG_SECONDARY, $this->secondaryEffect);
	}

	public function getPrimaryEffect() : int{ return $this->primaryEffect; }

	public function setPrimaryEffect(int $primaryEffect) : void{ $this->primaryEffect = $primaryEffect; }

	public function getSecondaryEffect() : int{ return $this->secondaryEffect; }

	public function setSecondaryEffect(int $secondaryEffect) : void{ $this->secondaryEffect = $secondaryEffect; }

	private function calculateTier() : int{
		$world = $this->position->getWorld();
		$validIds = [
			VanillaBlocks::IRON()->getTypeId(),
			VanillaBlocks::GOLD()->getTypeId(),
			VanillaBlocks::DIAMOND()->getTypeId(),
			VanillaBlocks::EMERALD()->getTypeId(),
			VanillaBlocks::NETHERITE()->getTypeId(),
		];
		$bx = $this->position->getFloorX();
		$by = $this->position->getFloorY();
		$bz = $this->position->getFloorZ();
		for($tier = 1; $tier <= 4; $tier++){
			$y = $by - $tier;
			for($x = $bx - $tier; $x <= $bx + $tier; $x++){
				for($z = $bz - $tier; $z <= $bz + $tier; $z++){
					if(!in_array($world->getBlockAt($x, $y, $z)->getTypeId(), $validIds, true)){
						return $tier - 1;
					}
				}
			}
		}
		return 4;
	}

	public function updateBeacon() : void{
		$tier = $this->calculateTier();
		if($tier === 0 || $this->primaryEffect === 0){
			return;
		}
		$primaryEffect = EffectIdMap::getInstance()->fromId($this->primaryEffect);
		if($primaryEffect === null){
			return;
		}
		$range = 10 + $tier * 10;
		$duration = 180;
		$primaryAmplifier = ($tier === 4 && $this->secondaryEffect === $this->primaryEffect) ? 1 : 0;
		$secondaryEffect = ($tier === 4 && $this->secondaryEffect !== 0 && $this->secondaryEffect !== $this->primaryEffect)
			? EffectIdMap::getInstance()->fromId($this->secondaryEffect)
			: null;
		$world = $this->position->getWorld();
		$pos = $this->position;
		$bb = new AxisAlignedBB(
			$pos->x - $range, $world->getMinY(), $pos->z - $range,
			$pos->x + $range + 1, $world->getMaxY(), $pos->z + $range + 1
		);
		foreach($world->getNearbyEntities($bb) as $entity){
			if($entity instanceof Living){
				$entity->getEffects()->add(new EffectInstance($primaryEffect, $duration, $primaryAmplifier));
				if($secondaryEffect !== null){
					$entity->getEffects()->add(new EffectInstance($secondaryEffect, $duration, 0));
				}
			}
		}
	}
}
