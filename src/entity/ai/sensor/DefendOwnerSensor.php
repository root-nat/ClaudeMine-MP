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
use pocketmine\entity\ai\WolfCombatLogic;
use pocketmine\entity\Living;
use pocketmine\entity\Wolf;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

/**
 * Decides who a wolf should fight and records it in COMBAT_TARGET (read by {@link \pocketmine\entity\ai\goal\WolfAttackGoal}),
 * mirroring vanilla's damage-driven wolf target goals rather than mere proximity. A wolf retaliates against the live
 * entity in HURT_BY (its last attacker), and a tamed wolf strikes back at whoever last damaged its owner — read straight
 * from the owner's last damage cause, so a projectile resolves to its shooter and a bystander mob that merely wandered
 * near the owner is ignored. The chosen foe is re-snapshotted with its current position each scan so the wolf tracks a
 * moving target, and is dropped once it is dead, out of range or gone. Sitting or baby wolves never engage. The pure
 * choice lives in {@link WolfCombatLogic}; this sensor only resolves the two live candidates around it.
 */
final class DefendOwnerSensor implements Sensor{

	public function __construct(
		private float $range = 16.0,
		private int $scanInterval = 2
	){}

	public function getScanIntervalTicks() : int{
		return $this->scanInterval;
	}

	public function sense(Living $owner, Memory $memory) : void{
		if(!$owner instanceof Wolf){
			return;
		}
		if($owner->isSitting() || $owner->isBaby()){
			//a wolf told to stay, or a pup, never fights — also skips all the lookups below
			$memory->erase(MemoryModuleType::COMBAT_TARGET);
			return;
		}

		$world = $owner->getWorld();
		$ownerPos = $owner->getPosition();
		$rangeSq = $this->range ** 2;
		$ownerPlayer = $owner->isTamed() ? $owner->findOwnerPlayer() : null;
		$ownerId = $ownerPlayer?->getId();

		$hurtById = null;
		$hurtByEngageable = false;
		$hurtByEntity = null;
		$hurtBy = $memory->get(MemoryModuleType::HURT_BY);
		if($hurtBy instanceof TargetCandidate){
			$hurtById = $hurtBy->entityId;
			$attacker = $world->getEntity($hurtById);
			if($attacker instanceof Living && $attacker->isAlive() && $attacker->getPosition()->distanceSquared($ownerPos) <= $rangeSq){
				$hurtByEngageable = true;
				$hurtByEntity = $attacker;
			}
		}

		$ownerAttackerId = null;
		$ownerAttackerEngageable = false;
		$ownerAttacker = null;
		if($ownerPlayer !== null){
			$cause = $ownerPlayer->getLastDamageCause();
			//EntityDamageByChildEntityEvent is a subclass, so a shot owner resolves to the shooter, not the projectile
			if($cause instanceof EntityDamageByEntityEvent){
				$damager = $cause->getDamager();
				if($damager instanceof Living && $damager !== $owner && $damager->isAlive() && $damager->getPosition()->distanceSquared($ownerPos) <= $rangeSq){
					$ownerAttackerId = $damager->getId();
					$ownerAttackerEngageable = true;
					$ownerAttacker = $damager;
				}
			}
		}

		$chosenId = WolfCombatLogic::chooseTargetId($owner->isSitting(), $ownerId, $hurtById, $hurtByEngageable, $ownerAttackerId, $ownerAttackerEngageable);
		if($chosenId === null){
			$memory->erase(MemoryModuleType::COMBAT_TARGET);
			return;
		}

		if($hurtByEntity !== null && $hurtByEntity->getId() === $chosenId){
			$target = $hurtByEntity;
		}elseif($ownerAttacker !== null && $ownerAttacker->getId() === $chosenId){
			$target = $ownerAttacker;
		}else{
			$memory->erase(MemoryModuleType::COMBAT_TARGET);
			return;
		}

		$pos = $target->getPosition();
		//short TTL so a skipped scan can never leave a stale foe locked in; a live scan refreshes it well before then
		$memory->set(
			MemoryModuleType::COMBAT_TARGET,
			new TargetCandidate($target->getId(), $pos->x, $pos->y, $pos->z, true, $target instanceof Player),
			$this->scanInterval + 1
		);
	}
}
