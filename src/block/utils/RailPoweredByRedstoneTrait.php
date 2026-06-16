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

namespace pocketmine\block\utils;

use pocketmine\data\runtime\RuntimeDataDescriber;

trait RailPoweredByRedstoneTrait{
	use PoweredByRedstoneTrait;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		parent::describeBlockOnlyState($w);
		$w->bool($this->powered);
	}

	public function onPostPlace() : void{
		parent::onPostPlace();
		$this->updateRailPowerState();
	}

	public function onNearbyBlockChange() : void{
		parent::onNearbyBlockChange(); //BaseRail checks it still has support (may break the rail)
		$this->updateRailPowerState();
	}

	/**
	 * Toggles the rail's powered state from the redstone power it is receiving (a powered rail accelerates/brakes carts,
	 * an activator rail triggers them). Only direct power is read; the vanilla 8-block powered-rail chain is not modelled.
	 */
	private function updateRailPowerState() : void{
		$world = $this->position->getWorld();
		if(!$world->getBlock($this->position)->hasSameTypeId($this)){
			return; //the rail was just broken by the support check in parent::onNearbyBlockChange
		}
		$receiving = $this->isReceivingRedstonePower();
		if($receiving !== $this->powered){
			$this->powered = $receiving;
			$world->setBlock($this->position, $this);
		}
	}
}
