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

namespace pocketmine\entity\villager;

use pocketmine\utils\Utils;
use function max;
use function min;

/**
 * A villager's gossip memory: per-player accumulated gossip values, from which a player's reputation is derived. Pure
 * logic implementing the vanilla reputation rules (caps, decay, gossip sharing, signed reputation weighting), so the
 * whole player-reputation model can be unit-tested without a live world.
 */
final class GossipContainer{
	private const SHARE_FRACTION_DENOMINATOR = 20;

	/** @var array<string, array<string, int>> player key => gossip type value => amount */
	private array $gossip = [];

	public function add(string $playerKey, GossipType $type, int $amount) : void{
		if($amount <= 0){
			return;
		}
		$current = $this->gossip[$playerKey][$type->value] ?? 0;
		$this->gossip[$playerKey][$type->value] = min($type->maxValue(), $current + $amount);
	}

	public function getValue(string $playerKey, GossipType $type) : int{
		return $this->gossip[$playerKey][$type->value] ?? 0;
	}

	/**
	 * Returns the player's overall reputation: the signed-weighted sum of all gossip about them.
	 */
	public function getReputation(string $playerKey) : int{
		$reputation = 0;
		foreach(Utils::stringifyKeys($this->gossip[$playerKey] ?? []) as $typeValue => $amount){
			$type = GossipType::from($typeValue);
			$reputation += $amount * $type->reputationWeight();
		}
		return $reputation;
	}

	/**
	 * @return string[] keys of all players this villager holds gossip about
	 */
	public function getKnownPlayers() : array{
		return array_keys($this->gossip);
	}

	/**
	 * Decays every stored gossip value by its per-day decay, removing entries that reach zero. Vanilla runs this once
	 * per in-game day.
	 */
	public function decay() : void{
		foreach(Utils::stringifyKeys($this->gossip) as $playerKey => $byType){
			foreach(Utils::stringifyKeys($byType) as $typeValue => $amount){
				$type = GossipType::from($typeValue);
				$decayed = max(0, $amount - $type->decayPerDay());
				if($decayed === 0){
					unset($this->gossip[$playerKey][$typeValue]);
				}else{
					$this->gossip[$playerKey][$typeValue] = $decayed;
				}
			}
			if(($this->gossip[$playerKey] ?? null) === []){
				unset($this->gossip[$playerKey]);
			}
		}
	}

	/**
	 * Shares a portion of this villager's gossip into another container, capped by each type's transfer value. Used when
	 * two villagers gossip, spreading reputation through the village.
	 */
	public function shareWith(GossipContainer $other) : void{
		foreach(Utils::stringifyKeys($this->gossip) as $playerKey => $byType){
			foreach(Utils::stringifyKeys($byType) as $typeValue => $amount){
				$type = GossipType::from($typeValue);
				$transfer = min($type->transferValue(), (int) ($amount / self::SHARE_FRACTION_DENOMINATOR) + 1);
				if($transfer > 0){
					$other->add($playerKey, $type, $transfer);
				}
			}
		}
	}
}
