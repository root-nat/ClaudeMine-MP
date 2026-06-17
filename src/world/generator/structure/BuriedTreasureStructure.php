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
 * Buried treasure: a single loot chest (the Heart of the Sea cache) sunk a few blocks under the ocean floor. The chest's
 * contents are filled by {@link OverworldStructureFurnisher}.
 *
 * The chest sits in the anchor column but well BELOW groundY, so the surface scan (which finds the seabed) stays stable
 * across chunks - exactly like {@link FossilStructure}'s buried bones. Fully fixed geometry.
 */
final class BuriedTreasureStructure extends Structure{

	public const MAX_RADIUS = 2;
	public const SALT = 0x5a170c;
	public const RARITY = 22;

	/** @var array{int, int, int} anchor-relative loot chest position (buried under the seabed) */
	public const CHEST_OFFSET = [0, -4, 0];

	private int $originX = 0;
	private int $originY = 0;
	private int $originZ = 0;

	public function __construct(
		private int $chestStateId
	){}

	public function canPlace(GenerationVolume $volume, int $x, int $y, int $z) : bool{
		return ($y - 5) > $volume->getMinY();
	}

	public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		$this->originX = $x;
		$this->originY = $y;
		$this->originZ = $z;

		[$cdx, $cdy, $cdz] = self::CHEST_OFFSET;
		$this->set($volume, $cdx, $cdy, $cdz, $this->chestStateId);
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
