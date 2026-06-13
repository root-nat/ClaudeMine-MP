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
use pocketmine\entity\ai\goal\LookAtPlayerGoal;
use pocketmine\entity\ai\goal\PanicGoal;
use pocketmine\entity\ai\goal\RandomStrollGoal;
use pocketmine\entity\ai\sensor\HurtBySensor;
use pocketmine\entity\ai\sensor\NearestPlayersSensor;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;

/**
 * Base class for passive farm-style animals (cows, pigs, sheep, chickens...). Provides the common idle/panic/look
 * behaviour and Bedrock baby state, on top of the {@link AbstractMob} AI core. Species add their own size, drops and
 * any extra goals.
 */
abstract class Animal extends AbstractMob implements Ageable{

	private const TAG_BABY = "Baby"; //TAG_Byte

	protected bool $baby = false;

	public function isBaby() : bool{
		return $this->baby;
	}

	public function setBaby(bool $baby = true) : void{
		$this->baby = $baby;
		$this->networkPropertiesDirty = true;
		//getInitialSizeInfo() depends on the baby flag, so re-apply the size to update the hitbox
		$this->setSize($this->getInitialSizeInfo());
	}

	protected function registerBehaviour() : void{
		$this->addSensor(new HurtBySensor());
		$this->addSensor(new NearestPlayersSensor($this->getFollowRange()));

		$this->addGoal(1, new PanicGoal());
		$this->addGoal(6, new LookAtPlayerGoal());
		$this->addGoal(7, new RandomStrollGoal());
		$this->registerExtraGoals();
	}

	/**
	 * Hook for species-specific goals (breeding, tempting, following parent...).
	 */
	protected function registerExtraGoals() : void{
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->baby);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_BABY, $this->baby ? 1 : 0);
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->baby = $nbt->getByte(self::TAG_BABY, 0) !== 0;
		parent::initEntity($nbt);
		//the Entity constructor sized us as an adult before the baby flag was loaded; correct the hitbox now
		if($this->baby){
			$this->setSize($this->getInitialSizeInfo());
		}
	}
}
