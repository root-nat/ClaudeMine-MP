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

use pocketmine\block\utils\DyeColor;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\entity\ai\goal\FollowOwnerGoal;
use pocketmine\entity\ai\sensor\OwnerSensor;
use pocketmine\item\Dye;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\SmokeParticle;
use function min;
use function mt_rand;

/**
 * Base class for pets the player can tame and own (wolves, cats...). Provides the whole tameable lifecycle on top of
 * {@link Animal}: taming a wild one, ownership, sit/stand, a dyeable collar (red by default, only rendered once tamed),
 * following the owner and teleporting to them when left too far behind, plus feeding the tamed pet to heal or breed it.
 * Species fill in which item tames them, which food they eat, and any extra behaviour (e.g. a wolf's combat).
 *
 * @phpstan-consistent-constructor
 */
abstract class TameableAnimal extends Animal{

	private const TAG_TAMED = "Tamed"; //TAG_Byte
	private const TAG_SITTING = "Sitting"; //TAG_Byte
	private const TAG_OWNER = "OwnerUUID"; //TAG_String
	private const TAG_COLLAR = "CollarColor"; //TAG_Byte (Bedrock dye-colour id)

	protected bool $tamed = false;
	protected bool $sitting = false;
	protected ?string $ownerUuid = null;
	protected int $collarColor = 14; //DyeColor::RED, the vanilla default collar

	public function isTamed() : bool{ return $this->tamed; }

	public function isSitting() : bool{ return $this->sitting; }

	public function setSitting(bool $sitting) : void{
		if($this->sitting !== $sitting){
			$this->sitting = $sitting;
			$this->networkPropertiesDirty = true;
		}
	}

	/** Bedrock dye-colour id (0-15) of the collar; only visible while the pet is tamed. */
	public function getCollarColor() : int{ return $this->collarColor; }

	public function setCollarColor(int $color) : void{
		if($this->collarColor !== $color){
			$this->collarColor = $color;
			$this->networkPropertiesDirty = true;
		}
	}

	public function isOwnedBy(Player $player) : bool{
		return $this->tamed && $this->ownerUuid === $player->getUniqueId()->toString();
	}

	public function findOwnerPlayer() : ?Player{
		if(!$this->tamed || $this->ownerUuid === null){
			return null;
		}
		foreach($this->getWorld()->getPlayers() as $player){
			if($player->getUniqueId()->toString() === $this->ownerUuid){
				return $player;
			}
		}
		return null;
	}

	/** How far (squared) the owner may get before a standing pet teleports to them. */
	protected function getTeleportDistanceSq() : float{
		return 24.0 * 24.0;
	}

	/**
	 * Tries to tame this wild pet from a right-click. Return true if the item was a taming attempt (and was consumed),
	 * false to let the normal animal interaction run. Use {@link self::attemptTame()} for the standard consume + chance.
	 */
	abstract protected function tryTameWith(Player $player, Item $item) : bool;

	/**
	 * Tries to feed this tamed, owned pet from a right-click (heal if hurt, otherwise breed). Return true if the item was
	 * its food (and was handled), false otherwise. Use {@link self::feedHealOrBreed()} for the standard behaviour.
	 */
	abstract protected function tryFeedTamed(Player $player, Item $item) : bool;

	/** Hook run right after this pet becomes tamed (directly or as a tamed-born baby); e.g. a wolf raises its bite. */
	protected function onTamed() : void{
	}

	protected function attemptTame(Player $player, Item $item, int $oneInChance) : void{
		$this->consumeFeedItem($player, $item);
		if(mt_rand(0, $oneInChance - 1) === 0){
			$this->tame($player);
		}else{
			$this->spawnParticleCloud(new SmokeParticle());
		}
	}

	protected function tame(Player $player) : void{
		$this->tamed = true;
		$this->ownerUuid = $player->getUniqueId()->toString();
		$this->sitting = false;
		$this->setMaxHealth($this->getDefaultMaxHealth());
		$this->setHealth($this->getMaxHealth());
		$this->onTamed();
		$this->networkPropertiesDirty = true;
		$this->spawnParticleCloud(new HeartParticle());
	}

	protected function feedHealOrBreed(Player $player, Item $item, int $healAmount) : void{
		if($this->getHealth() < $this->getMaxHealth()){
			$this->consumeFeedItem($player, $item);
			$this->setHealth(min($this->getMaxHealth(), $this->getHealth() + $healAmount));
			$this->spawnParticleCloud(new HeartParticle());
		}elseif(!$this->isBaby() && $this->breedCooldownTicks <= 0 && !$this->isInLove()){
			$this->consumeFeedItem($player, $item);
			$this->setInLove();
		}
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		if(!$this->tamed){
			if($this->tryTameWith($player, $item)){
				return true;
			}
			return parent::onInteract($player, $clickPos);
		}
		if($this->isOwnedBy($player)){
			if($this->tryFeedTamed($player, $item)){
				return true;
			}
			if($item instanceof Dye){
				//dye recolours the collar (one dye consumed) and never toggles sitting, like vanilla
				$newColor = DyeColorIdMap::getInstance()->toId($item->getColor());
				if($newColor !== $this->collarColor){
					$this->setCollarColor($newColor);
					if($player->hasFiniteResources()){
						$item->pop();
						$player->getInventory()->setItemInHand($item);
					}
				}
				return true;
			}
			$this->setSitting(!$this->sitting);
			return true;
		}
		return parent::onInteract($player, $clickPos);
	}

	protected function createChild() : ?Animal{
		$baby = new static(Location::fromObject($this->location, $this->getWorld()));
		if($this->tamed && $this->ownerUuid !== null){
			//offspring are born already tamed to the same owner as their parents
			$baby->tamed = true;
			$baby->ownerUuid = $this->ownerUuid;
			$baby->collarColor = $this->collarColor;
			$baby->setMaxHealth($baby->getDefaultMaxHealth());
			$baby->setHealth($baby->getMaxHealth());
			$baby->onTamed();
			$baby->networkPropertiesDirty = true;
		}
		return $baby;
	}

	protected function registerExtraGoals() : void{
		$this->addSensor(new OwnerSensor());
		$this->addGoal(3, new FollowOwnerGoal());
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->closed || !$this->isAlive()){
			return $hasUpdate;
		}

		if($this->sitting){
			//a sitting pet stays planted, overriding any idle wandering the goals applied this tick
			$this->motion = $this->motion->withComponents(0.0, $this->motion->y, 0.0);
		}elseif($this->tamed && ($this->ticksLived % 20) === 0){
			$player = $this->findOwnerPlayer();
			if($player !== null && $player->getWorld() === $this->getWorld() && $player->getPosition()->distanceSquared($this->location) > $this->getTeleportDistanceSq()){
				$this->teleport($player->getPosition());
			}
		}

		return $hasUpdate;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::TAMED, $this->tamed);
		$properties->setGenericFlag(EntityMetadataFlags::SITTING, $this->sitting);
		$properties->setByte(EntityMetadataProperties::COLOR, $this->collarColor);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_TAMED, $this->tamed ? 1 : 0);
		$nbt->setByte(self::TAG_SITTING, $this->sitting ? 1 : 0);
		if($this->ownerUuid !== null){
			$nbt->setString(self::TAG_OWNER, $this->ownerUuid);
		}
		$nbt->setByte(self::TAG_COLLAR, $this->collarColor);
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->tamed = $nbt->getByte(self::TAG_TAMED, 0) !== 0;
		$this->sitting = $nbt->getByte(self::TAG_SITTING, 0) !== 0;
		$owner = $nbt->getString(self::TAG_OWNER, "");
		$this->ownerUuid = $owner !== "" ? $owner : null;
		$this->collarColor = $nbt->getByte(self::TAG_COLLAR, DyeColorIdMap::getInstance()->toId(DyeColor::RED));
		//tamed must be loaded before parent::initEntity, which reads getDefaultMaxHealth() (health depends on tamed)
		parent::initEntity($nbt);
	}
}
