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

namespace pocketmine\world\generator\structure;

use pocketmine\utils\Random;
use pocketmine\world\generator\carver\GenerationVolume;
use function abs;

/**
 * An amethyst geode: concentric spherical shells (smooth basalt -> calcite -> amethyst block) around a hollow interior
 * lined with budding amethyst, buried underground. No loot, no mobs.
 *
 * Centred on the anchor but sunk well below the surface, so it never touches the anchor column at or above groundY: the
 * surface scan stays stable across chunks (like {@link FossilStructure}). Geometry is fully fixed; budding-amethyst
 * placement is a fixed positional rule (clusters then grow from the budding blocks at runtime).
 */
final class AmethystGeodeStructure extends Structure{

	public const MAX_RADIUS = 5;
	public const SALT = 0x5a1709;
	public const RARITY = 24;

	private const DEPTH = 9; //the geode centre sits this far below the surface
	private const R = 5;

	private int $originX = 0;
	private int $originY = 0;
	private int $originZ = 0;

	public function __construct(
		private int $airStateId,
		private int $smoothBasaltStateId,
		private int $calciteStateId,
		private int $amethystStateId,
		private int $buddingAmethystStateId
	){}

	public function canPlace(GenerationVolume $volume, int $x, int $y, int $z) : bool{
		return ($y - (self::DEPTH + self::R + 1)) > $volume->getMinY();
	}

	public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		$this->originX = $x;
		$this->originY = $y;
		$this->originZ = $z;

		for($dx = -self::R; $dx <= self::R; ++$dx){
			for($dz = -self::R; $dz <= self::R; ++$dz){
				for($oy = -self::R; $oy <= self::R; ++$oy){
					$d2 = $dx * $dx + $oy * $oy + $dz * $dz;
					if($d2 > self::R * self::R){
						continue; //outside the geode
					}
					$state = match(true){
						$d2 > 16 => $this->smoothBasaltStateId, //outer shell
						$d2 > 9 => $this->calciteStateId, //middle shell
						$d2 > 4 => ((($dx * 7 + $oy * 5 + $dz) & 7) === 0) ? $this->buddingAmethystStateId : $this->amethystStateId, //inner shell
						default => $this->airStateId //hollow core
					};
					$this->set($volume, $dx, -self::DEPTH + $oy, $dz, $state);
				}
			}
		}
	}

	private function set(GenerationVolume $volume, int $dx, int $dy, int $dz, int $stateId) : void{
		if(abs($dx) > self::MAX_RADIUS || abs($dz) > self::MAX_RADIUS){
			return;
		}
		$x = $this->originX + $dx;
		$y = $this->originY + $dy;
		$z = $this->originZ + $dz;
		if($volume->isInBounds($x, $y, $z)){
			$volume->setBlockStateId($x, $y, $z, $stateId);
		}
	}
}
