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
use pocketmine\entity\object\EvokerFangs;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function cos;
use function mt_rand;
use function sin;
use const M_PI;

/**
 * A spellcasting illager that keeps its distance and conjures a ring of biting {@link EvokerFangs} from the ground
 * around its target. Always drops a Totem of Undying. (Its vex-summoning and "wololo" spells are not yet modelled.)
 */
class Evoker extends Raider{

	/** Ticks between fang conjurings. */
	private const CAST_INTERVAL = 100;
	/** Number of jaws in the conjured ring. */
	private const FANG_RING_COUNT = 8;
	private const FANG_RING_RADIUS = 1.5;

	public static function getNetworkTypeId() : string{ return EntityIds::EVOCATION_ILLAGER; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.95, 0.6);
	}

	protected function getDefaultMaxHealth() : int{
		return 24;
	}

	public function getName() : string{
		return "Evoker";
	}

	protected function registerAttackGoals() : void{
		//keeps its distance (minRange 6) and conjures fangs on a long cooldown instead of meleeing
		$this->addGoal(2, new RangedAttackGoal(
			fn(TargetCandidate $target) => $this->castFangs($target),
			16.0,
			6.0,
			self::CAST_INTERVAL
		));
	}

	private function castFangs(TargetCandidate $target) : void{
		$world = $this->getWorld();
		//erupt a ring of jaws around the target so it bites regardless of range
		for($i = 0; $i < self::FANG_RING_COUNT; ++$i){
			$angle = (2 * M_PI / self::FANG_RING_COUNT) * $i;
			$pos = new Vector3(
				$target->x + cos($angle) * self::FANG_RING_RADIUS,
				$target->y,
				$target->z + sin($angle) * self::FANG_RING_RADIUS
			);
			$fang = new EvokerFangs(Location::fromObject($pos, $world, 0.0, 0.0));
			$fang->setOwningEntity($this);
			$fang->spawnToAll();
		}
	}

	public function getDrops() : array{
		//an evoker killed by a player always yields a totem of undying
		return [
			VanillaItems::TOTEM(),
			VanillaItems::EMERALD()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return 10;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::EVOKER_SPAWN_EGG();
	}
}
