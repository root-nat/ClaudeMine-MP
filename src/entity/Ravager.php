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

use pocketmine\entity\ai\goal\MeleeAttackGoal;
use pocketmine\entity\ai\target\TargetCandidate;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;

/**
 * The hulking siege beast of a raid: a slow, very tanky brute that gores its target and periodically lets out a roar
 * that flings nearby foes back. Its illager rider, stun-on-shield-block and saddle drop are not yet modelled.
 */
class Ravager extends Raider{

	private const ATTACK_DAMAGE = 12.0;
	/** Ticks between roar checks; it only actually roars (and resets the full cooldown) when foes are in range. */
	private const ROAR_INTERVAL = 100;
	private const ROAR_RANGE = 4.0;
	private const ROAR_DAMAGE = 6.0;

	private int $roarCooldown = self::ROAR_INTERVAL;

	public static function getNetworkTypeId() : string{ return EntityIds::RAVAGER; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(2.2, 1.95);
	}

	protected function getDefaultMaxHealth() : int{
		return 100;
	}

	public function getName() : string{
		return "Ravager";
	}

	protected function registerAttackGoals() : void{
		$this->addGoal(1, new MeleeAttackGoal());
	}

	public function attackEntity(TargetCandidate $target) : void{
		$victim = $this->getWorld()->getEntity($target->entityId);
		if($victim instanceof Living && $victim->isAlive()){
			$victim->attack(new EntityDamageByEntityEvent($this, $victim, EntityDamageEvent::CAUSE_ENTITY_ATTACK, self::ATTACK_DAMAGE));
			//a gore from the ravager tosses its victim up and away
			$dir = $victim->getPosition()->subtractVector($this->location)->normalize();
			$victim->setMotion($victim->getMotion()->add($dir->x * 0.5, 0.4, $dir->z * 0.5));
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->closed || !$this->isAlive()){
			return $hasUpdate;
		}

		$this->roarCooldown -= $tickDiff;
		if($this->roarCooldown <= 0){
			//roar on the full cooldown if it caught anyone; otherwise recheck soon so it doesn't waste the cooldown idle
			$this->roarCooldown = $this->roar() ? self::ROAR_INTERVAL : 20;
		}

		return $hasUpdate;
	}

	private function roar() : bool{
		$roared = false;
		foreach($this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(self::ROAR_RANGE, 2.0, self::ROAR_RANGE)) as $entity){
			//the roar hurts and flings the raid's victims (players, villagers, golems), never fellow hostiles
			if($entity instanceof Living && $entity !== $this && $entity->isAlive() && !($entity instanceof Monster)){
				$entity->attack(new EntityDamageByEntityEvent($this, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, self::ROAR_DAMAGE));
				$dir = $entity->getPosition()->subtractVector($this->location)->normalize();
				$entity->setMotion($entity->getMotion()->add($dir->x * 0.8, 0.5, $dir->z * 0.8));
				$roared = true;
			}
		}
		return $roared;
	}

	public function getDrops() : array{
		return [
			VanillaItems::EMERALD()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return 20;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::RAVAGER_SPAWN_EGG();
	}
}
