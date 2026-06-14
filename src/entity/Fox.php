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

use pocketmine\entity\ai\goal\AvoidEntityGoal;
use pocketmine\entity\ai\memory\MemoryModuleType;
use pocketmine\entity\ai\sensor\AvoidEntitySensor;
use pocketmine\entity\ai\target\TargetCandidate;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function array_map;
use function in_array;
use function mt_rand;

/**
 * The fox: a skittish, nocturnal animal. It curls up and sleeps through the day when nothing is around, but wakes and
 * bolts the moment a player approaches — unless that player is offering sweet berries, which tempts it closer and lets
 * you breed it. Flees and tempting are reused from the shared avoidance/temptation AI.
 */
class Fox extends Animal{

	/** A fox bolts from a player within this many blocks (unless the player holds its food). */
	private const FLEE_RANGE = 8.0;

	private const TAG_VARIANT = "Variant"; //TAG_Int
	private const TAG_TRUSTED = "TrustedPlayers"; //TAG_List<TAG_String> of player UUIDs
	/** Number of vanilla fox coats (red, snow). */
	private const VARIANT_COUNT = 2;

	protected bool $sleeping = false;
	protected int $variant = 0;
	/** UUID of the player who last fed this fox into love mode, passed on to any kit it produces. */
	protected ?string $lastFeederUuid = null;
	/** @var string[] UUIDs of players a bred kit trusts and will not flee. */
	protected array $trustedUuids = [];

	public static function getNetworkTypeId() : string{ return EntityIds::FOX; }

	protected function getBreedingSpecies() : ?string{ return BreedingHelper::FOX; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo($this->isBaby() ? 0.35 : 0.7, $this->isBaby() ? 0.3 : 0.6);
	}

	protected function getDefaultMaxHealth() : int{
		return 10;
	}

	public function getName() : string{
		return "Fox";
	}

	public function isSleeping() : bool{ return $this->sleeping; }

	public function setSleeping(bool $sleeping) : void{
		if($this->sleeping !== $sleeping){
			$this->sleeping = $sleeping;
			$this->networkPropertiesDirty = true;
		}
	}

	public function getVariant() : int{ return $this->variant; }

	public function setVariant(int $variant) : void{
		if($this->variant !== $variant){
			$this->variant = $variant;
			$this->networkPropertiesDirty = true;
		}
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		//feeding two adults their berries breeds them; remember the feeder so the kit will trust them (vanilla foxes)
		if($this->isBreedingFood($item) && !$this->isBaby() && $this->breedCooldownTicks <= 0 && !$this->isInLove()){
			$this->lastFeederUuid = $player->getUniqueId()->toString();
		}
		return parent::onInteract($player, $clickPos);
	}

	private function trusts(Player $player) : bool{
		return in_array($player->getUniqueId()->toString(), $this->trustedUuids, true);
	}

	protected function registerExtraGoals() : void{
		//flee players, except one offering berries (tempts it closer) or one a bred kit was raised to trust
		$this->addSensor(new AvoidEntitySensor(
			Player::class,
			self::FLEE_RANGE,
			5,
			fn(Entity $entity) => $entity instanceof Player && ($this->trusts($entity) || $this->isBreedingFood($entity->getInventory()->getItemInHand()))
		));
		$this->addGoal(3, new AvoidEntityGoal());
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->closed || !$this->isAlive()){
			return $hasUpdate;
		}

		$this->setSleeping($this->shouldSleep());
		if($this->sleeping){
			//a sleeping fox stays curled up, overriding any idle wandering the goals applied this tick
			$this->motion = $this->motion->withComponents(0.0, $this->motion->y, 0.0);
		}

		return $hasUpdate;
	}

	private function shouldSleep() : bool{
		if(!$this->onGround || $this->isInLove()){
			return false;
		}
		$memory = $this->getMemory();
		//awake whenever something just hurt it (so it can panic-flee), a player it fears is near, or a berry tempts it
		if(
			$memory->get(MemoryModuleType::HURT_BY) instanceof TargetCandidate ||
			$memory->get(MemoryModuleType::AVOID_TARGET) instanceof TargetCandidate ||
			$memory->get(MemoryModuleType::TEMPTING_PLAYER) instanceof TargetCandidate
		){
			return false;
		}
		return DaylightBurnRules::isDaytime($this->getWorld()->getTimeOfDay());
	}

	protected function createChild() : ?Animal{
		$baby = parent::createChild();
		if($baby instanceof Fox){
			//a kit takes after this parent's coat and trusts whoever fed this parent into love
			$baby->setVariant($this->variant);
			if($this->lastFeederUuid !== null){
				$baby->trustedUuids[] = $this->lastFeederUuid;
			}
		}
		return $baby;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::SLEEPING, $this->sleeping);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->variant);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_VARIANT, $this->variant);
		if($this->trustedUuids !== []){
			$nbt->setTag(self::TAG_TRUSTED, new ListTag(array_map(fn(string $uuid) => new StringTag($uuid), $this->trustedUuids)));
		}
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->variant = $nbt->getInt(self::TAG_VARIANT, mt_rand(0, self::VARIANT_COUNT - 1));
		$trustedTag = $nbt->getListTag(self::TAG_TRUSTED);
		if($trustedTag !== null){
			foreach($trustedTag->getValue() as $tag){
				if($tag instanceof StringTag){
					$this->trustedUuids[] = $tag->getValue();
				}
			}
		}
		parent::initEntity($nbt);
	}

	public function getDrops() : array{
		return [];
	}

	public function getXpDropAmount() : int{
		return $this->isBaby() ? 0 : mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::FOX_SPAWN_EGG();
	}
}
