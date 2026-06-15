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

use pocketmine\entity\ai\goal\BreedGoal;
use pocketmine\entity\ai\goal\FollowParentGoal;
use pocketmine\entity\ai\goal\MeleeAttackGoal;
use pocketmine\entity\ai\goal\RandomStrollGoal;
use pocketmine\entity\ai\sensor\BreedingSensor;
use pocketmine\entity\ai\sensor\HurtBySensor;
use pocketmine\entity\ai\sensor\NearestPlayersSensor;
use pocketmine\entity\ai\target\TargetCandidate;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\Dimension;
use function mt_rand;

/**
 * A hulking boar of the Crimson Forest: hostile to players, it charges and gores its target, tossing them into the air.
 * Unusually for a hostile mob it can be bred (with crimson fungus) and has a baby form. Left outside the Nether it
 * trembles and zombifies into a {@link Zoglin}. (Its fear of warped fungus / respawn anchors is not yet modelled.)
 *
 * @phpstan-consistent-constructor
 */
class Hoglin extends Animal{

	private const ATTACK_DAMAGE = 8.0;
	/** Ticks spent outside the Nether before the hoglin zombifies into a zoglin (15s). */
	private const ZOMBIFY_TICKS = 300;

	private int $zombifyTicks = 0;

	public static function getNetworkTypeId() : string{ return EntityIds::HOGLIN; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return $this->isBaby() ? new EntitySizeInfo(0.7, 0.7) : new EntitySizeInfo(1.4, 1.4);
	}

	protected function getDefaultMaxHealth() : int{
		return 40;
	}

	public function getName() : string{
		return "Hoglin";
	}

	protected function getBreedingSpecies() : ?string{
		return BreedingHelper::HOGLIN;
	}

	protected function registerBehaviour() : void{
		//a hoglin is hostile (it charges players) yet breedable - wire combat on top of the Animal breeding/baby machinery
		$this->addSensor(new HurtBySensor());
		$this->addSensor(new NearestPlayersSensor($this->getFollowRange()));
		$this->addSensor(new BreedingSensor($this->getFollowRange()));

		//breeding (a fed pair) and a baby trailing its parent outrank hunting, so an in-love adult or a baby isn't locked
		//into charging a nearby player; an idle adult still hunts on sight (hoglins are hostile, not neutral)
		$this->addGoal(1, new BreedGoal());
		$this->addGoal(2, new FollowParentGoal());
		$this->addGoal(3, new MeleeAttackGoal());
		$this->addGoal(7, new RandomStrollGoal());
	}

	public function attackEntity(TargetCandidate $target) : void{
		if($this->isBaby()){
			return; //baby hoglins can't hurt anything
		}
		$victim = $this->getWorld()->getEntity($target->entityId);
		if($victim instanceof Living && $victim->isAlive()){
			$victim->attack(new EntityDamageByEntityEvent($this, $victim, EntityDamageEvent::CAUSE_ENTITY_ATTACK, self::ATTACK_DAMAGE));
			//the hoglin's signature gore: it tosses its victim up and back
			$dir = $victim->getPosition()->subtractVector($this->location)->normalize();
			$victim->setMotion($victim->getMotion()->add($dir->x * 0.6, 0.45, $dir->z * 0.6));
		}
	}

	/**
	 * Whether this beast still turns into a zoglin outside the Nether. A {@link Zoglin} (already zombified) overrides this.
	 */
	protected function canZombify() : bool{
		return true;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->closed || !$this->isAlive() || $this->isFlaggedForDespawn()){
			return $hasUpdate;
		}

		//away from the Nether a hoglin trembles and turns into a zoglin
		if($this->canZombify() && $this->getWorld()->getDimension() !== Dimension::NETHER){
			$this->zombifyTicks += $tickDiff;
			if($this->zombifyTicks >= self::ZOMBIFY_TICKS){
				$this->zombify();
				return $hasUpdate;
			}
			$hasUpdate = true;
		}else{
			$this->zombifyTicks = 0;
		}

		//hostile boars obey the distance despawn like other monsters (Animal itself never despawns); a name tag spares one
		if($this->getNameTag() === "" && ($this->ticksLived % MobDespawnRules::CHECK_INTERVAL_TICKS) === 0){
			$nearest = null;
			foreach($this->getWorld()->getPlayers() as $player){
				if(!$player->isAlive()){
					continue;
				}
				$distSq = $player->getPosition()->distanceSquared($this->location);
				if($nearest === null || $distSq < $nearest){
					$nearest = $distSq;
				}
			}
			if(MobDespawnRules::shouldDespawn($nearest, mt_rand(0, MobDespawnRules::RANDOM_DESPAWN_PER_CHECK_DENOM - 1))){
				$this->flagForDespawn();
			}
		}

		return $hasUpdate;
	}

	private function zombify() : void{
		$zoglin = new Zoglin(Location::fromObject($this->location, $this->getWorld()));
		$zoglin->setBaby($this->isBaby());
		$zoglin->setHealth($this->getHealth());
		$zoglin->spawnToAll();
		$this->flagForDespawn();
	}

	public function getDrops() : array{
		if($this->isBaby()){
			return [];
		}
		return [
			VanillaItems::RAW_PORKCHOP()->setCount(mt_rand(2, 4)),
			VanillaItems::LEATHER()->setCount(mt_rand(0, 1)),
		];
	}

	public function getXpDropAmount() : int{
		return $this->isBaby() ? 0 : 3;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::HOGLIN_SPAWN_EGG();
	}
}
