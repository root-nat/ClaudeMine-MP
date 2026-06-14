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
use pocketmine\entity\ai\sensor\AvoidEntitySensor;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use function mt_rand;

/**
 * The skittish jungle cat. An ocelot runs away from any nearby player, UNLESS that player is holding its food (raw cod or
 * salmon) — then it lets itself be tempted closer, which is how you lure and breed them. Ocelots cannot be tamed (in
 * modern vanilla they only grant "trust"); approach slowly with fish to keep them near.
 */
class Ocelot extends Animal{

	/** Ocelots flee a player within this many blocks (unless the player holds their food). */
	private const FLEE_RANGE = 12.0;

	public static function getNetworkTypeId() : string{ return EntityIds::OCELOT; }

	protected function getBreedingSpecies() : ?string{ return BreedingHelper::OCELOT; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo($this->isBaby() ? 0.35 : 0.7, $this->isBaby() ? 0.3 : 0.6);
	}

	protected function getDefaultMaxHealth() : int{
		return 10;
	}

	public function getName() : string{
		return "Ocelot";
	}

	protected function registerExtraGoals() : void{
		//flee players, but not one offering fish — that player tempts it closer instead (see TemptGoal)
		$this->addSensor(new AvoidEntitySensor(
			Player::class,
			self::FLEE_RANGE,
			5,
			fn(Entity $entity) => $entity instanceof Player && $this->isBreedingFood($entity->getInventory()->getItemInHand())
		));
		//above TemptGoal (4) so a nearby threat still wins, but below panic/breed
		$this->addGoal(3, new AvoidEntityGoal());
	}

	public function getDrops() : array{
		return [];
	}

	public function getXpDropAmount() : int{
		return $this->isBaby() ? 0 : mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::OCELOT_SPAWN_EGG();
	}
}
