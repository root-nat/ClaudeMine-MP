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

use pocketmine\entity\ai\goal\PiglinGoldPickupGoal;
use pocketmine\entity\ai\goal\RandomStrollGoal;
use pocketmine\entity\ai\sensor\PiglinGoldPickupSensor;
use pocketmine\entity\ai\sensor\PiglinTargetSensor;
use pocketmine\entity\object\ItemEntity;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use function mt_rand;

/**
 * A gold-obsessed brute of the crimson forest and nether wastes. It is wary rather than mindless: it attacks a player
 * wearing no scrap of gold armour on sight, and any player who strikes a piglin enrages the whole sounder (see {@link
 * AbstractPiglin}/{@link PiglinTargetSensor}). Outside the Nether it zombifies into a {@link ZombifiedPiglin}. It
 * barters gold ingots handed to it OR thrown near it (it walks over, picks one up and admires it). (Hunting hoglins and
 * fear of soul fire / wither skeletons are not yet modelled.)
 */
class Piglin extends AbstractPiglin{

	/** Ticks a piglin admires a bartered gold ingot before handing back a reward (6s). */
	private const ADMIRE_TICKS = 120;

	private const TAG_BARTER = "BarterTicks"; //TAG_Int

	private int $barterTicks = 0;

	public static function getNetworkTypeId() : string{ return EntityIds::PIGLIN; }

	protected function getDefaultMaxHealth() : int{
		return 16;
	}

	public function getName() : string{
		return "Piglin";
	}

	protected function registerBehaviour() : void{
		//hostility is conditional (gold armour pacifies; a blow enrages the pack) - driven by the piglin target sensor
		$this->addSensor(new PiglinTargetSensor($this->getFollowRange()));
		//notice gold dropped nearby and go fetch it to barter (the "throw gold to a piglin" trade); scan briskly (every 5t)
		//so a candidate that vanishes (item eaten/despawned) stops suppressing combat quickly
		$this->addSensor(new PiglinGoldPickupSensor($this->getFollowRange(), 5));
		//fetching gold preempts attacking: a piglin is distracted by gold even away from a target it would otherwise fight
		$this->addGoal(0, new PiglinGoldPickupGoal());
		$this->registerAttackGoals();
		$this->addGoal(8, new RandomStrollGoal());
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		//hand a piglin a gold ingot and (unless it's enraged or already admiring one) it pockets it to barter a reward back
		if($item->getTypeId() === ItemTypeIds::GOLD_INGOT && !$this->isAngry() && $this->barterTicks <= 0){
			if($player->hasFiniteResources()){
				$item->pop();
				$player->getInventory()->setItemInHand($item);
			}
			$this->startBarter();
			return true;
		}
		return parent::onInteract($player, $clickPos);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff); //AbstractPiglin runs the anger timer
		if($this->closed || !$this->isAlive() || $this->isFlaggedForDespawn()){
			return $hasUpdate;
		}

		//finished admiring a bartered gold ingot: hand back a reward from the barter table
		if($this->barterTicks > 0){
			$this->barterTicks -= $tickDiff;
			if($this->barterTicks <= 0){
				$this->barterTicks = 0;
				$this->dropBarter();
				//lower the gold back down: drop the admiring pose flag and put the sword back in its hand
				$this->networkPropertiesDirty = true;
				$this->broadcastHeldItem();
			}
			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	protected function canZombify() : bool{
		return true; //a living piglin reverts to a zombified piglin away from the Nether
	}

	public function isPersistent() : bool{
		//don't let the distance despawn cull a piglin mid-admire, or the player loses the gold ingot with no reward
		return parent::isPersistent() || $this->barterTicks > 0;
	}

	protected function getDisplayedHeldItem() : Item{
		//while admiring a bartered ingot the piglin holds the gold itself, not its sword
		return $this->barterTicks > 0 ? VanillaItems::GOLD_INGOT() : parent::getDisplayedHeldItem();
	}

	protected function isMovementFrozen() : bool{
		//stand still to examine the gold ingot while admiring, instead of strolling or chasing
		return $this->barterTicks > 0;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		//the admiring pose (both hands raising the gold to its face) is driven by this client flag
		$properties->setGenericFlag(EntityMetadataFlags::ADMIRING, $this->barterTicks > 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_BARTER, $this->barterTicks);
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->barterTicks = $nbt->getInt(self::TAG_BARTER, 0);
	}

	protected function onBeforeZombify() : void{
		//a piglin still admiring a bartered ingot when it transforms hands the reward back first, instead of eating the gold
		if($this->barterTicks > 0){
			$this->dropBarter();
			$this->barterTicks = 0;
		}
	}

	/**
	 * Whether this piglin is willing to fetch and barter dropped gold right now: calm (not enraged) and not already
	 * admiring a piece. Read by {@link PiglinGoldPickupSensor}.
	 */
	public function wantsBarterPickup() : bool{
		return !$this->isAngry() && $this->barterTicks <= 0;
	}

	/**
	 * Grabs a single gold ingot off the given dropped-item entity (by id) and starts admiring it. Returns true if the
	 * pickup happened. Called by {@link PiglinGoldPickupGoal} once the piglin reaches the gold it walked to.
	 */
	public function pickUpBarterGold(int $entityId) : bool{
		if(!$this->wantsBarterPickup()){
			return false;
		}
		$entity = $this->getWorld()->getEntity($entityId);
		if(!$entity instanceof ItemEntity || $entity->isFlaggedForDespawn()){
			return false;
		}
		$dropped = $entity->getItem();
		if($dropped->getTypeId() !== ItemTypeIds::GOLD_INGOT || $entity->getPosition()->distanceSquared($this->location) > 4.0){
			return false;
		}

		//take a single ingot off the dropped stack; any extra ingots stay on the ground
		$remaining = $dropped->getCount() - 1;
		if($remaining <= 0){
			$entity->flagForDespawn();
		}else{
			$entity->setStackSize($remaining);
		}
		$this->startBarter();
		return true;
	}

	private function startBarter() : void{
		$this->barterTicks = self::ADMIRE_TICKS;
		//raise the gold ingot to its face: swap the displayed hand item and light up the admiring pose flag
		$this->networkPropertiesDirty = true;
		$this->broadcastHeldItem();
	}

	private function dropBarter() : void{
		$reward = PiglinBarterLogic::roll(mt_rand(0, 0x7FFFFFFF), mt_rand(0, 0x7FFFFFFF));
		$this->getWorld()->dropItem($this->location->add(0.0, 1.0, 0.0), $reward);
	}

	public function getDrops() : array{
		return []; //piglins yield no notable drops, just experience
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::PIGLIN_SPAWN_EGG();
	}
}
