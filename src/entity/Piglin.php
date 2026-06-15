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
use pocketmine\entity\ai\sensor\PiglinTargetSensor;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\Dimension;
use function mt_rand;

/**
 * A gold-obsessed brute of the crimson forest and nether wastes. It is wary rather than mindless: it attacks a player
 * wearing no scrap of gold armour on sight, and any player who strikes a piglin enrages the whole sounder (see {@link
 * AbstractPiglin}/{@link PiglinTargetSensor}). Outside the Nether it zombifies into a {@link ZombifiedPiglin}.
 * (Bartering, gold pick-up/admiration, hunting hoglins, and fear of soul fire / wither skeletons are not yet modelled.)
 */
class Piglin extends AbstractPiglin{

	/** Ticks spent outside the Nether before the piglin zombifies (15s). */
	private const ZOMBIFY_TICKS = 300;
	/** Ticks a piglin admires a bartered gold ingot before handing back a reward (6s). */
	private const ADMIRE_TICKS = 120;

	private const TAG_ZOMBIFY = "ZombifyTicks"; //TAG_Int
	private const TAG_BARTER = "BarterTicks"; //TAG_Int

	private int $zombifyTicks = 0;
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
			$this->barterTicks = self::ADMIRE_TICKS;
			//raise the gold ingot to its face: swap the displayed hand item and light up the admiring pose flag
			$this->networkPropertiesDirty = true;
			$this->broadcastHeldItem();
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

		//away from the Nether a piglin trembles and turns into a zombified piglin
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

		return $hasUpdate;
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
		$nbt->setInt(self::TAG_ZOMBIFY, $this->zombifyTicks);
		$nbt->setInt(self::TAG_BARTER, $this->barterTicks);
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->zombifyTicks = $nbt->getInt(self::TAG_ZOMBIFY, 0);
		$this->barterTicks = $nbt->getInt(self::TAG_BARTER, 0);
	}

	private function zombify() : void{
		//a piglin still admiring a bartered ingot when it transforms hands the reward back first, instead of eating the gold
		if($this->barterTicks > 0){
			$this->dropBarter();
			$this->barterTicks = 0;
		}
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
