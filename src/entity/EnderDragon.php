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

use pocketmine\entity\boss\BossBar;
use pocketmine\entity\boss\DragonFightState;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\types\BossBarColor;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;

/**
 * The ender dragon. Health, the boss bar, and crystal-shielded damage resolution are wired up via {@link
 * DragonFightState}; the full flight path around the pillars and perch behaviour are a documented TODO requiring a live
 * End world and client validation.
 */
class EnderDragon extends Living{

	private const TAG_ALIVE_CRYSTALS = "PMMPAliveCrystals";

	private BossBar $bossBar;
	private DragonFightState $fight;
	private float $lastBroadcastPercentage = 1.0;

	public static function getNetworkTypeId() : string{ return EntityIds::ENDER_DRAGON; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(8.0, 16.0);
	}

	protected function getInitialDragMultiplier() : float{ return 0.0; }

	protected function getInitialGravity() : float{ return 0.0; } //the dragon flies

	public function getName() : string{
		return "Ender Dragon";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setMaxHealth(200);
		$this->bossBar = new BossBar("Ender Dragon", BossBarColor::PURPLE, true);
		$this->fight = new DragonFightState($nbt->getInt(self::TAG_ALIVE_CRYSTALS, DragonFightState::PILLAR_COUNT));
	}

	public function getFightState() : DragonFightState{
		return $this->fight;
	}

	public function attack(EntityDamageEvent $source) : void{
		//crystals heal/shield the dragon until destroyed; only perch hits land while they remain
		$atPerch = $this->fight->getPhase()->isPerched();
		$resolved = $this->fight->resolveDamage($source->getFinalDamage(), $atPerch);
		if($resolved <= 0.0){
			$source->cancel();
		}
		parent::attack($source);
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
				$this->bossBar->getColor() //$darkenScreen was dropped from show() in 1.26.30; $color is now the 4th arg
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
		return 12000;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_ALIVE_CRYSTALS, $this->fight->getAliveCrystals());
		return $nbt;
	}
}
