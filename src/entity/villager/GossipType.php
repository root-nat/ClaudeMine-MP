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

/**
 * The kinds of gossip villagers accumulate about a player, mirroring the vanilla reputation system. Each type caps the
 * stored value, decays over time, transfers a portion when villagers share gossip, and contributes to a player's
 * reputation with a signed weight.
 */
enum GossipType : string{
	case MAJOR_NEGATIVE = "major_negative";
	case MINOR_NEGATIVE = "minor_negative";
	case MINOR_POSITIVE = "minor_positive";
	case MAJOR_POSITIVE = "major_positive";
	case TRADING = "trading";

	/**
	 * Maximum stored value for this gossip type.
	 */
	public function maxValue() : int{
		return match($this){
			self::MAJOR_NEGATIVE, self::MAJOR_POSITIVE => 100,
			self::MINOR_NEGATIVE, self::MINOR_POSITIVE => 200,
			self::TRADING => 25,
		};
	}

	/**
	 * Amount this gossip decays by per decay tick (vanilla: once per in-game day).
	 */
	public function decayPerDay() : int{
		return match($this){
			self::MAJOR_NEGATIVE => 10,
			self::MINOR_NEGATIVE => 20,
			self::MINOR_POSITIVE => 1,
			self::MAJOR_POSITIVE => 0,
			self::TRADING => 2,
		};
	}

	/**
	 * How much of this gossip transfers to another villager when gossip is shared.
	 */
	public function transferValue() : int{
		return match($this){
			self::MAJOR_NEGATIVE, self::TRADING, self::MINOR_NEGATIVE => 20,
			self::MINOR_POSITIVE => 5,
			self::MAJOR_POSITIVE => 100,
		};
	}

	/**
	 * Signed multiplier applied to the stored value when computing a player's overall reputation.
	 */
	public function reputationWeight() : int{
		return match($this){
			self::MAJOR_NEGATIVE => -5,
			self::MINOR_NEGATIVE => -1,
			self::MINOR_POSITIVE => 1,
			self::MAJOR_POSITIVE => 5,
			self::TRADING => 1,
		};
	}
}
