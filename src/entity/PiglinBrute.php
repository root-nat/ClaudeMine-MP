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

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

/**
 * The elite guardian of bastion remnants: a bigger, golden-axe-wielding piglin that is ALWAYS hostile. Unlike its lesser
 * kin it cannot be pacified by gold armour, ignores bartering, and never flees - it just charges any survival player on
 * sight (the on-sight player targeting it inherits from {@link Monster}). Like every living piglin it zombifies into a
 * {@link ZombifiedPiglin} away from the Nether. Striking one still enrages the sounder through {@link AbstractPiglin}.
 */
class PiglinBrute extends AbstractPiglin{

	protected const ATTACK_DAMAGE = 7.0;

	public static function getNetworkTypeId() : string{ return EntityIds::PIGLIN_BRUTE; }

	protected function getDefaultMaxHealth() : int{
		return 50;
	}

	public function getName() : string{
		return "Piglin Brute";
	}

	protected function canZombify() : bool{
		return true; //a brute dragged out of the Nether reverts to a zombified piglin like any living piglin
	}

	protected function getHeldWeapon() : Item{
		return VanillaItems::GOLDEN_AXE();
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::PIGLIN_BRUTE_SPAWN_EGG();
	}
}
