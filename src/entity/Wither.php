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
use pocketmine\entity\boss\BossBar;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\types\BossBarColor;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;

class Wither extends Monster{

	private BossBar $bossBar;
	private float $lastBroadcastPercentage = 1.0;

	public static function getNetworkTypeId() : string{ return EntityIds::WITHER; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(3.5, 0.9);
	}

	protected function getInitialGravity() : float{
		return 0.0; //the wither hovers
	}

	protected function getDefaultMaxHealth() : int{
		return 300;
	}

	public function getName() : string{
		return "Wither";
	}

	protected function registerBehaviour() : void{
		$this->bossBar = new BossBar("Wither", BossBarColor::PURPLE, true);
		parent::registerBehaviour();
	}

	protected function registerAttackGoals() : void{
		//TODO: ranged wither-skull volleys; melee pursuit is a functional placeholder
		$this->addGoal(2, new MeleeAttackGoal());
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		$percentage = $this->getMaxHealth() > 0 ? $this->getHealth() / $this->getMaxHealth() : 0.0;
		if($percentage !== $this->lastBroadcastPercentage){
			$this->lastBroadcastPercentage = $percentage;
			$this->bossBar->setPercentage($percentage);
			foreach($this->getViewers() as $player){
				$player->getNetworkSession()->sendDataPacket(BossEventPacket::healthPercent($this->getId(), $percentage));
			}
		}
		return $hasUpdate;
	}

	public function spawnTo(Player $player) : void{
		$alreadySpawned = isset($this->hasSpawned[spl_object_id($player)]);
		parent::spawnTo($player);
		if(!$alreadySpawned && isset($this->hasSpawned[spl_object_id($player)])){
			$player->getNetworkSession()->sendDataPacket(BossEventPacket::show(
				$this->getId(),
				$this->bossBar->getTitle(),
				$this->bossBar->getPercentage(),
				$this->bossBar->isDarkenScreen(),
				$this->bossBar->getColor()
			));
		}
	}

	public function despawnFrom(Player $player, bool $send = true) : void{
		if(isset($this->hasSpawned[spl_object_id($player)]) && $send){
			$player->getNetworkSession()->sendDataPacket(BossEventPacket::hide($this->getId()));
		}
		parent::despawnFrom($player, $send);
	}

	public function getXpDropAmount() : int{
		return 50;
	}

	public function getDrops() : array{
		return [VanillaItems::NETHER_STAR()];
	}

	public function getPickedItem() : ?Item{
		return null;
	}
}
