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

use pocketmine\entity\ai\goal\RandomStrollGoal;
use pocketmine\entity\ai\sensor\HurtBySensor;
use pocketmine\entity\ai\sensor\RaiderTargetSensor;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use function min;

/**
 * Shared base for the illagers and beasts that make up raids (pillager, vindicator, evoker, witch, ravager). It hunts a
 * village's defenders - players, villagers and iron golems - via {@link RaiderTargetSensor} rather than only players, so
 * the same mobs threaten the village during a raid and behave hostilely toward players when spawned loose.
 *
 * A raider may be a CAPTAIN: it carries the ominous banner on its head and, when a player kills it, grants that player
 * Bad Omen - which seeds a raid at the next village they enter. Species still declare their own attack goal(s) in
 * registerAttackGoals().
 */
abstract class Raider extends Monster{

	private const TAG_CAPTAIN = "IsCaptain"; //TAG_Byte
	/** Bad Omen granted by killing a captain lasts this long - long enough to walk to a village. */
	private const BAD_OMEN_DURATION = 6000;
	/** Bad Omen tops out at level 5 (amplifier 4); each further captain kill bumps it toward that. */
	private const MAX_BAD_OMEN_AMPLIFIER = 4;

	private bool $captain = false;

	protected function registerBehaviour() : void{
		$this->addSensor(new RaiderTargetSensor($this->getFollowRange()));
		$this->addSensor(new HurtBySensor());

		$this->registerAttackGoals();
		$this->addGoal(8, new RandomStrollGoal());
	}

	public function isCaptain() : bool{
		return $this->captain;
	}

	/**
	 * Marks this raider as a patrol captain: it carries the ominous banner, won't be culled while loaded, and grants Bad
	 * Omen to whoever kills it.
	 */
	public function setCaptain(bool $captain = true) : void{
		$this->captain = $captain;
		if($captain){
			$this->setPersistent();
		}
		$this->networkPropertiesDirty = true;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		if($nbt->getByte(self::TAG_CAPTAIN, 0) !== 0){
			$this->setCaptain();
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_CAPTAIN, $this->captain ? 1 : 0);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		//the Bedrock client draws the floating ominous banner over a captain purely from this flag - no held/worn item
		$properties->setGenericFlag(EntityMetadataFlags::ILLAGER_CAPTAIN, $this->captain);
	}

	protected function onDeath() : void{
		parent::onDeath();
		if(!$this->captain){
			return;
		}
		//a captain felled by a player (directly or by their projectile) hands its killer Bad Omen
		$cause = $this->getLastDamageCause();
		if($cause instanceof EntityDamageByEntityEvent){
			$killer = $cause->getDamager();
			if($killer instanceof Player){
				$this->grantBadOmen($killer);
			}
		}
	}

	private function grantBadOmen(Player $player) : void{
		$effects = $player->getEffects();
		$current = $effects->get(VanillaEffects::BAD_OMEN());
		//stacking captain kills raise the omen level (and so the raid's strength), capped at level 5
		$amplifier = $current !== null ? min(self::MAX_BAD_OMEN_AMPLIFIER, $current->getAmplifier() + 1) : 0;
		$effects->add(new EffectInstance(VanillaEffects::BAD_OMEN(), self::BAD_OMEN_DURATION, $amplifier));
	}
}
