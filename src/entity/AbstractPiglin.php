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
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use pocketmine\world\Dimension;

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
	/** Ticks spent outside the Nether before a living piglin (or brute) zombifies (15s). */
	protected const ZOMBIFY_TICKS = 300;

	private const TAG_ZOMBIFY = "ZombifyTicks"; //TAG_Int

	private ?int $angerTargetId = null;
	private int $angerTicks = 0;
	private int $zombifyTicks = 0;

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
		if($this->closed || !$this->isAlive() || $this->isFlaggedForDespawn()){
			return $hasUpdate;
		}

		if($this->angerTicks > 0){
			$this->angerTicks -= $tickDiff;
			if($this->angerTicks <= 0){
				$this->calmDown();
			}
			$hasUpdate = true;
		}

		//a living piglin or brute dragged out of the Nether trembles and turns into a zombified piglin (the undead don't)
		if($this->canZombify()){
			if($this->getWorld()->getDimension() !== Dimension::NETHER){
				$this->zombifyTicks += $tickDiff;
				if($this->zombifyTicks >= self::ZOMBIFY_TICKS){
					$this->zombify();
					return $hasUpdate;
				}
				$hasUpdate = true;
			}else{
				$this->zombifyTicks = 0;
			}
		}

		return $hasUpdate;
	}

	/**
	 * Whether this piglin converts to a {@link ZombifiedPiglin} when away from the Nether. True for living piglins and
	 * brutes; the already-undead zombified piglin stays as it is (the default).
	 */
	protected function canZombify() : bool{
		return false;
	}

	private function zombify() : void{
		$this->onBeforeZombify();
		$zombie = new ZombifiedPiglin(Location::fromObject($this->location, $this->getWorld()));
		$zombie->setHealth($this->getHealth());
		//carry an active grudge across the transformation, so attacking a piglin that then zombifies doesn't reset its aggro
		$targetId = $this->getAngerTargetId();
		if($targetId !== null){
			$zombie->angerAt($targetId, $this->getRemainingAngerTicks());
		}
		$zombie->spawnToAll();
		$this->flagForDespawn();
	}

	/**
	 * Hook run just before this piglin turns into a zombified piglin, while it is still the living entity. Subclasses use
	 * it to settle pending state (e.g. a piglin handing back a bartered reward it was still admiring).
	 */
	protected function onBeforeZombify() : void{
		//NOOP
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_ZOMBIFY, $this->zombifyTicks);
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->zombifyTicks = $nbt->getInt(self::TAG_ZOMBIFY, 0);
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
