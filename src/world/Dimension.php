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

namespace pocketmine\world;

use pocketmine\network\mcpe\protocol\types\DimensionIds;
use function strtolower;

enum Dimension{
	case OVERWORLD;
	case NETHER;
	case THE_END;

	public static function fromGeneratorName(string $generatorName) : self{
		return match(strtolower($generatorName)){
			"nether", "hell" => self::NETHER,
			"end", "the_end", "ending" => self::THE_END,
			default => self::OVERWORLD
		};
	}

	public static function fromSaveName(string $saveName) : ?self{
		return match($saveName){
			"overworld" => self::OVERWORLD,
			"nether" => self::NETHER,
			"the_end" => self::THE_END,
			default => null
		};
	}

	public function getSaveName() : string{
		return match($this){
			self::OVERWORLD => "overworld",
			self::NETHER => "nether",
			self::THE_END => "the_end"
		};
	}

	/**
	 * @phpstan-return DimensionIds::*
	 */
	public function getNetworkId() : int{
		return match($this){
			self::OVERWORLD => DimensionIds::OVERWORLD,
			self::NETHER => DimensionIds::NETHER,
			self::THE_END => DimensionIds::THE_END
		};
	}

	/**
	 * Returns the multiplier used to convert coordinates from this dimension to the overworld.
	 */
	public function getCoordinateScale() : float{
		return match($this){
			self::NETHER => 8.0,
			default => 1.0
		};
	}

	public function hasSkyLight() : bool{
		return $this === self::OVERWORLD;
	}

	public function hasWeather() : bool{
		return $this === self::OVERWORLD;
	}

	public function hasDayNightCycle() : bool{
		return $this === self::OVERWORLD;
	}
}
