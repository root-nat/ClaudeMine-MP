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

namespace pocketmine\entity\object;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Monster;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

/**
 * The biting jaws an Evoker conjures from the ground: it rises for a moment, snaps once at whatever stands over it for
 * magic damage, then sinks away. Summoned by {@link \pocketmine\entity\Evoker}; not naturally spawnable and not saved.
 */
class EvokerFangs extends Entity{

	/** Ticks the jaws rise before they snap. */
	private const WARMUP_TICKS = 10;
	/** Total lifetime before the jaws sink away. */
	private const LIFETIME_TICKS = 22;
	private const DAMAGE = 6.0;

	private int $age = 0;
	private bool $bitten = false;
	private int $warmupDelay = 0;

	public static function getNetworkTypeId() : string{ return EntityIds::EVOCATION_FANG; }

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.8, 0.5); }

	protected function getInitialGravity() : float{ return 0.0; }

	protected function getInitialDragMultiplier() : float{ return 0.0; }

	/** Extra ticks to wait before snapping, so a summoned line of jaws can erupt in sequence. */
	public function setWarmupDelay(int $ticks) : void{
		$this->warmupDelay = $ticks;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->closed){
			return $hasUpdate;
		}

		$this->age += $tickDiff;
		if(!$this->bitten && $this->age >= self::WARMUP_TICKS + $this->warmupDelay){
			$this->bitten = true;
			$this->bite();
		}
		if($this->age >= self::LIFETIME_TICKS + $this->warmupDelay){
			$this->flagForDespawn();
		}

		return true;
	}

	private function bite() : void{
		$owner = $this->getOwningEntity();
		//snap at the raid's victims (players, villagers, golems) but never at the summoner or fellow hostiles
		foreach($this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(0.2, 1.0, 0.2)) as $entity){
			if($entity instanceof Living && $entity !== $owner && $entity->isAlive() && !($entity instanceof Monster)){
				$entity->attack(new EntityDamageByEntityEvent($owner ?? $this, $entity, EntityDamageEvent::CAUSE_MAGIC, self::DAMAGE));
			}
		}
	}
}
