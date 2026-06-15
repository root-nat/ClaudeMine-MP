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
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;

/**
 * Shared base for the piglin kin (piglin, piglin brute, zombified piglin): fire/lava immunity, a golden weapon, and the
 * grudge state machine behind their pack anger - being struck by a player enrages this piglin, a species sensor spreads
 * that grudge to nearby kin, and an enraged piglin won't be culled by the distance despawn until it calms. Subclasses
 * decide WHO to be hostile to (their own targeting sensor) in registerBehaviour().
 */
abstract class AbstractPiglin extends Monster{

	/** How long a piglin stays enraged at its offender after a provocation (30s). */
	protected const ANGER_DURATION_TICKS = 600;
	protected const ATTACK_DAMAGE = 5.0;

	private ?int $angerTargetId = null;
	private int $angerTicks = 0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.95, 0.6);
	}

	public function isFireProof() : bool{
		return true; //immune to fire and lava, like all things born of the Nether
	}

	public function isAngry() : bool{
		return $this->angerTicks > 0 && $this->angerTargetId !== null;
	}

	public function getAngerTargetId() : ?int{
		return $this->angerTargetId;
	}

	public function getRemainingAngerTicks() : int{
		return $this->angerTicks;
	}

	/**
	 * Enrages this piglin at the given player (by entity id). A fresh provocation re-arms the full duration; adopting a
	 * pack-mate's grudge passes that mate's REMAINING time so the whole sounder's anger only ever counts down.
	 */
	public function angerAt(int $playerId, int $durationTicks = self::ANGER_DURATION_TICKS) : void{
		$this->angerTargetId = $playerId;
		$this->angerTicks = $durationTicks;
	}

	public function calmDown() : void{
		$this->angerTargetId = null;
		$this->angerTicks = 0;
	}

	public function isPersistent() : bool{
		//an enraged piglin won't be culled by the distance despawn mid-swarm; normal despawn resumes once it calms
		return parent::isPersistent() || $this->isAngry();
	}

	protected function registerAttackGoals() : void{
		$this->addGoal(1, new MeleeAttackGoal());
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if($source->isCancelled()){
			return;
		}
		//a blow from a survival player (directly or via their projectile) enrages this piglin; the sensor spreads it
		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager instanceof Player && $damager->isSurvival()){
				$this->angerAt($damager->getId());
			}
		}
	}

	public function attackEntity(TargetCandidate $target) : void{
		$victim = $this->getWorld()->getEntity($target->entityId);
		if($victim instanceof Living && $victim->isAlive()){
			$this->broadcastAnimation(new ArmSwingAnimation($this));
			$victim->attack(new EntityDamageByEntityEvent($this, $victim, EntityDamageEvent::CAUSE_ENTITY_ATTACK, static::ATTACK_DAMAGE));
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->closed || !$this->isAlive()){
			return $hasUpdate;
		}

		if($this->angerTicks > 0){
			$this->angerTicks -= $tickDiff;
			if($this->angerTicks <= 0){
				$this->calmDown();
			}
			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		$this->sendHeldItem($player); //arm every viewer's copy with whatever this piglin is currently holding
	}

	/**
	 * Pushes the piglin's currently displayed hand item to a single viewer's client.
	 */
	private function sendHeldItem(Player $player) : void{
		$session = $player->getNetworkSession();
		$session->sendDataPacket(MobEquipmentPacket::create(
			$this->getId(),
			ItemStackWrapper::legacy($session->getTypeConverter()->coreItemStackToNet($this->getDisplayedHeldItem())),
			0,
			0,
			ContainerIds::INVENTORY
		));
	}

	/**
	 * Re-pushes the displayed hand item to every viewer; call after the held item changes at runtime (e.g. a piglin
	 * swapping its sword for the gold ingot it is admiring).
	 */
	protected function broadcastHeldItem() : void{
		foreach($this->getViewers() as $viewer){
			$this->sendHeldItem($viewer);
		}
	}

	/**
	 * The item a viewer sees in this piglin's hand right now. Defaults to its weapon; a bartering piglin overrides this
	 * to show off the gold ingot it is admiring.
	 */
	protected function getDisplayedHeldItem() : Item{
		return $this->getHeldWeapon();
	}

	/**
	 * The weapon a viewer sees in this piglin's hand. Golden sword by default; the brute wields a golden axe.
	 */
	protected function getHeldWeapon() : Item{
		return VanillaItems::GOLDEN_SWORD();
	}
}
