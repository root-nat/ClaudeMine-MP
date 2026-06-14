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

use pocketmine\entity\ai\goal\RabbitHopGoal;
use pocketmine\entity\ai\sensor\AvoidEntitySensor;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function mt_rand;

/**
 * The skittish rabbit. It moves only by hopping (never gliding), wanders idly, and bolts away from any nearby player
 * unless that player is offering it a carrot — then it lets itself be tempted closer, which is how you lure and breed
 * them. All of its movement is owned by {@link RabbitHopGoal}.
 */
class Rabbit extends Animal{

	/** A rabbit bolts from a player within this many blocks (unless the player holds its food). */
	private const FLEE_RANGE = 8.0;

	private const TAG_VARIANT = "Variant"; //TAG_Int
	/** Number of vanilla rabbit coats (brown, white, black, white-splotched, gold, salt-and-pepper). */
	private const VARIANT_COUNT = 6;

	protected int $variant = 0;

	public static function getNetworkTypeId() : string{ return EntityIds::RABBIT; }

	protected function getBreedingSpecies() : ?string{ return BreedingHelper::RABBIT; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo($this->isBaby() ? 0.25 : 0.5, $this->isBaby() ? 0.2 : 0.4);
	}

	protected function getDefaultMaxHealth() : int{
		return 3;
	}

	public function getName() : string{
		return "Rabbit";
	}

	public function getVariant() : int{ return $this->variant; }

	public function setVariant(int $variant) : void{
		if($this->variant !== $variant){
			$this->variant = $variant;
			$this->networkPropertiesDirty = true;
		}
	}

	protected function registerExtraGoals() : void{
		//flee players, but not one offering a carrot — that player tempts it closer instead
		$this->addSensor(new AvoidEntitySensor(
			Player::class,
			self::FLEE_RANGE,
			5,
			fn(Entity $entity) => $entity instanceof Player && $this->isBreedingFood($entity->getInventory()->getItemInHand())
		));
		//top priority: hopping owns all movement, replacing the gliding stroll/panic/tempt/follow goals
		$this->addGoal(0, new RabbitHopGoal());
	}

	protected function createChild() : ?Animal{
		$baby = parent::createChild();
		if($baby instanceof Rabbit){
			//a kit takes after this parent's coat
			$baby->setVariant($this->variant);
		}
		return $baby;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->variant);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_VARIANT, $this->variant);
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->variant = $nbt->getInt(self::TAG_VARIANT, mt_rand(0, self::VARIANT_COUNT - 1));
		parent::initEntity($nbt);
	}

	public function getDrops() : array{
		return [
			VanillaItems::RABBIT_HIDE()->setCount(mt_rand(0, 1)),
			VanillaItems::RAW_RABBIT()->setCount(mt_rand(0, 1)),
		];
	}

	public function getXpDropAmount() : int{
		return $this->isBaby() ? 0 : mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::RABBIT_SPAWN_EGG();
	}
}
