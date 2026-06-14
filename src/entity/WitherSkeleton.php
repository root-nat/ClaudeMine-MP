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
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function in_array;
use function mt_rand;

/**
 * Nether melee skeleton: taller than a normal skeleton, immune to fire, and its hits inflict the Wither effect.
 */
class WitherSkeleton extends Monster{

	public static function getNetworkTypeId() : string{ return EntityIds::WITHER_SKELETON; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(2.4, 0.7);
	}

	public function getName() : string{
		return "Wither Skeleton";
	}

	protected function registerAttackGoals() : void{
		$this->addGoal(2, new MeleeAttackGoal());
	}

	public function attackEntity(TargetCandidate $target) : void{
		parent::attackEntity($target);
		$victim = $this->getWorld()->getEntity($target->entityId);
		if($victim instanceof Living && $victim->isAlive()){
			$victim->getEffects()->add(new EffectInstance(VanillaEffects::WITHER(), 200, 0));
		}
	}

	public function attack(EntityDamageEvent $source) : void{
		if(in_array($source->getCause(), [EntityDamageEvent::CAUSE_FIRE, EntityDamageEvent::CAUSE_FIRE_TICK, EntityDamageEvent::CAUSE_LAVA], true)){
			$source->cancel();
		}
		parent::attack($source);
	}

	public function getDrops() : array{
		return [
			VanillaItems::COAL()->setCount(mt_rand(0, 1)),
			VanillaItems::BONE()->setCount(mt_rand(0, 2))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::WITHER_SKELETON_SPAWN_EGG();
	}
}
