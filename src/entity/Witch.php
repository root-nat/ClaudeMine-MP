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

use pocketmine\entity\ai\goal\RangedAttackGoal;
use pocketmine\entity\ai\target\TargetCandidate;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\projectile\SplashPotion;
use pocketmine\item\Item;
use pocketmine\item\PotionType;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;
use function sqrt;

/**
 * A hag that lobs splash potions of Harming at its target from range, the spellcasting-adjacent support of raids.
 * (Potion-drinking self-buffs and the witch's vanilla magic resistance are not yet modelled.)
 */
class Witch extends Raider{

	public static function getNetworkTypeId() : string{ return EntityIds::WITCH; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.95, 0.6);
	}

	protected function getDefaultMaxHealth() : int{
		return 26;
	}

	public function getName() : string{
		return "Witch";
	}

	protected function registerAttackGoals() : void{
		$this->addGoal(2, new RangedAttackGoal(
			fn(TargetCandidate $target) => $this->throwPotionAt($target),
			12.0,
			3.0,
			40
		));
	}

	private function throwPotionAt(TargetCandidate $target) : void{
		$origin = $this->getEyePos();
		$potion = new SplashPotion(
			Location::fromObject($origin, $this->getWorld(), $this->location->getYaw(), $this->location->getPitch()),
			$this,
			PotionType::HARMING
		);

		$dx = $target->x - $origin->x;
		$dy = ($target->y + 1.0) - $origin->y;
		$dz = $target->z - $origin->z;
		$horizontal = sqrt(($dx * $dx) + ($dz * $dz));
		//lob it in an arc so the heavier-than-an-arrow potion lands on the target
		$direction = new Vector3($dx, $dy + $horizontal * 0.3, $dz);

		$potion->setMotion($direction->normalize()->multiply(0.75));
		$potion->spawnToAll();
		$this->broadcastAnimation(new ArmSwingAnimation($this));
	}

	public function getDrops() : array{
		//a sampling of the witch's vanilla loot table
		$pool = [
			VanillaItems::GLOWSTONE_DUST(),
			VanillaItems::REDSTONE_DUST(),
			VanillaItems::SUGAR(),
			VanillaItems::SPIDER_EYE(),
			VanillaItems::GLASS_BOTTLE(),
			VanillaItems::GUNPOWDER(),
			VanillaItems::STICK(),
		];

		$drops = [];
		foreach($pool as $item){
			if(mt_rand(0, 2) === 0){
				$drops[] = $item->setCount(mt_rand(1, 2));
			}
		}
		return $drops;
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::WITCH_SPAWN_EGG();
	}
}
