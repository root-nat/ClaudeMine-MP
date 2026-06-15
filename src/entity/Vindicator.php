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
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use function mt_rand;

/**
 * An axe-wielding illager that charges its target and hits hard - the melee shock troop of pillager patrols and raids.
 */
class Vindicator extends Raider{

	/** Per-hit melee damage (vanilla normal difficulty). Vindicators hit much harder than a zombie. */
	private const ATTACK_DAMAGE = 13.0;

	public static function getNetworkTypeId() : string{ return EntityIds::VINDICATOR; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.95, 0.6);
	}

	protected function getDefaultMaxHealth() : int{
		return 24;
	}

	public function getName() : string{
		return "Vindicator";
	}

	protected function registerAttackGoals() : void{
		$this->addGoal(1, new MeleeAttackGoal());
	}

	public function attackEntity(TargetCandidate $target) : void{
		$victim = $this->getWorld()->getEntity($target->entityId);
		if($victim instanceof Living && $victim->isAlive()){
			$this->broadcastAnimation(new ArmSwingAnimation($this)); //swing the axe down
			$victim->attack(new EntityDamageByEntityEvent($this, $victim, EntityDamageEvent::CAUSE_ENTITY_ATTACK, self::ATTACK_DAMAGE));
		}
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);

		//put an iron axe in the vindicator's hand so every viewer sees it armed
		$session = $player->getNetworkSession();
		$session->sendDataPacket(MobEquipmentPacket::create(
			$this->getId(),
			ItemStackWrapper::legacy($session->getTypeConverter()->coreItemStackToNet(VanillaItems::IRON_AXE())),
			0,
			0,
			ContainerIds::INVENTORY
		));
	}

	public function getDrops() : array{
		//occasionally drops the iron axe it was wielding
		return mt_rand(0, 11) === 0 ? [VanillaItems::IRON_AXE()] : [];
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::VINDICATOR_SPAWN_EGG();
	}
}
