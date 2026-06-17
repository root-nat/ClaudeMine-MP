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
 * A jungle temple: a mossy cobblestone hall, sunk a block into the jungle floor, holding two loot chests (filled by
 * {@link OverworldStructureFurnisher}). Built OFFSET from the anchor so the anchor column (0,0) stays open ground for a
 * stable surface scan. Geometry is fully fixed; the mossy speckle is a fixed positional rule (no Random).
 */
final class JungleTempleStructure extends Structure{

	public const MAX_RADIUS = 11;
	public const SALT = 0x5a1707;
	public const RARITY = 22;

	private const CENTER = 6; //hall centre X offset from the anchor; (0,0) stays open ground
	private const HALF_X = 4;
	private const HALF_Z = 3;

	/** @var list<array{int, int, int}> anchor-relative loot chest positions */
	public const CHEST_OFFSETS = [[self::CENTER - 3, 0, 2], [self::CENTER + 3, 0, -2]];

	private int $originX = 0;
	private int $originY = 0;
	private int $originZ = 0;

	public function __construct(
		private int $airStateId,
		private int $cobblestoneStateId,
		private int $mossyCobblestoneStateId,
		private int $chestStateId
	){}

	public function canPlace(GenerationVolume $volume, int $x, int $y, int $z) : bool{
		return ($y - 2) > $volume->getMinY() && ($y + 5) < $volume->getMaxY();
	}

	public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		$this->originX = $x;
		$this->originY = $y;
		$this->originZ = $z;

		$c = self::CENTER;
		//floor (sunk one block), four walls (mossy-speckled) and a roof; hollow interior
		for($a = -self::HALF_X; $a <= self::HALF_X; ++$a){
			for($b = -self::HALF_Z; $b <= self::HALF_Z; ++$b){
				$onWall = abs($a) === self::HALF_X || abs($b) === self::HALF_Z;
				$this->set($volume, $c + $a, -1, $b, $this->mix($a, $b, -1)); //floor
				$this->set($volume, $c + $a, 4, $b, $this->mix($a, $b, 4)); //roof
				for($dy = 0; $dy <= 3; ++$dy){
					$this->set($volume, $c + $a, $dy, $b, $onWall ? $this->mix($a, $b, $dy) : $this->airStateId);
				}
			}
		}
		//doorway through the -X wall, facing the open approach
		$this->set($volume, $c - self::HALF_X, 0, 0, $this->airStateId);
		$this->set($volume, $c - self::HALF_X, 1, 0, $this->airStateId);

		//two loot chests resting on the hall floor
		foreach(self::CHEST_OFFSETS as [$cdx, $cdy, $cdz]){
			$this->set($volume, $cdx, $cdy, $cdz, $this->chestStateId);
		}
	}

	private function mix(int $a, int $b, int $dy) : int{
		//fixed positional rule (no Random) so the weathered mossy speckle is identical across chunks
		return ((($a * 3 + $b * 7 + $dy) & 3) === 0) ? $this->mossyCobblestoneStateId : $this->cobblestoneStateId;
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
