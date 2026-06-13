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

namespace pocketmine\entity\boss;

use function max;

/**
 * Pure state machine for the ender dragon fight: which phase the dragon is in, how many pillar end-crystals are still
 * alive (they heal and shield the dragon), and how incoming damage resolves. Mirrors the vanilla rule that the dragon
 * is invulnerable to body hits while crystals remain and is only meaningfully damaged when perched with the crystals
 * destroyed. The crystal-respawn check (4 crystals on the exit portal) is also modelled here. Fully unit-testable.
 */
final class DragonFightState{
	public const PILLAR_COUNT = 10;
	public const RESPAWN_CRYSTALS_REQUIRED = 4;
	public const HEAL_PER_CRYSTAL_PER_CYCLE = 1.0;

	private DragonPhase $phase = DragonPhase::CIRCLING;
	private int $aliveCrystals;

	public function __construct(int $aliveCrystals = self::PILLAR_COUNT){
		$this->aliveCrystals = max(0, $aliveCrystals);
	}

	public function getPhase() : DragonPhase{
		return $this->phase;
	}

	public function setPhase(DragonPhase $phase) : void{
		$this->phase = $phase;
	}

	public function getAliveCrystals() : int{
		return $this->aliveCrystals;
	}

	public function destroyCrystal() : void{
		$this->aliveCrystals = max(0, $this->aliveCrystals - 1);
	}

	/**
	 * Whether the dragon is currently being healed/shielded by at least one pillar crystal.
	 */
	public function isHealing() : bool{
		return $this->aliveCrystals > 0;
	}

	/**
	 * Heal applied per heal cycle from the surviving crystals.
	 */
	public function healPerCycle() : float{
		return $this->aliveCrystals * self::HEAL_PER_CRYSTAL_PER_CYCLE;
	}

	/**
	 * Resolves how much of an incoming hit actually sticks: body hits are fully negated while any crystal heals the
	 * dragon; perch hits always land. This is what forces players to destroy the crystals first.
	 */
	public function resolveDamage(float $incoming, bool $atPerch) : float{
		if($atPerch || $this->phase->isPerched()){
			return $incoming;
		}
		return $this->isHealing() ? 0.0 : $incoming;
	}

	/**
	 * Whether the dragon can be respawned given the number of end crystals currently placed on the exit portal.
	 */
	public static function canRespawn(int $crystalsOnPortal) : bool{
		return $crystalsOnPortal >= self::RESPAWN_CRYSTALS_REQUIRED;
	}
}
