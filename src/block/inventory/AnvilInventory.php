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

namespace pocketmine\block\inventory;

use pocketmine\block\utils\AnvilHelper;
use pocketmine\block\utils\AnvilResult;
use pocketmine\inventory\SimpleInventory;
use pocketmine\inventory\TemporaryInventory;
use pocketmine\world\Position;

class AnvilInventory extends SimpleInventory implements BlockInventory, TemporaryInventory{
	use BlockInventoryTrait;

	public const SLOT_INPUT = 0;
	public const SLOT_MATERIAL = 1;

	public function __construct(Position $holder){
		$this->holder = $holder;
		parent::__construct(2);
	}

	public function getInput() : \pocketmine\item\Item{
		return $this->getItem(self::SLOT_INPUT);
	}

	public function getMaterial() : \pocketmine\item\Item{
		return $this->getItem(self::SLOT_MATERIAL);
	}

	/**
	 * Computes the vanilla anvil result (repaired/combined/renamed item and its XP cost) for the current input and
	 * material, or null if the combination is invalid. $newName is the requested rename (null = keep name).
	 */
	public function computeResult(?string $newName) : ?AnvilResult{
		return AnvilHelper::tryCombine($this->getInput(), $this->getMaterial(), $newName);
	}
}
