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
 * A shipwreck: a broken wooden hull resting on the ocean floor, with a mast and two loot chests in the hold (filled by
 * {@link OverworldStructureFurnisher}). Anchored on the OCEAN floor (the surface scan skips water and finds the seabed).
 *
 * Built OFFSET from the anchor so the anchor column (0,0) stays open seabed (stable surface scan). Geometry is fully
 * fixed - the "wreck" (which hull planks are missing) is a fixed positional rule, not Random.
 */
final class ShipwreckStructure extends Structure{

	public const MAX_RADIUS = 10;
	public const SALT = 0x5a170b;
	public const RARITY = 28;

	private const CENTER = 5; //hull centre X offset from the anchor; (0,0) stays open seabed

	/** @var list<array{int, int, int}> anchor-relative loot chest positions (in the hold) */
	public const CHEST_OFFSETS = [[self::CENTER - 2, 1, 0], [self::CENTER + 2, 1, 0]];

	private int $originX = 0;
	private int $originY = 0;
	private int $originZ = 0;

	public function __construct(
		private int $planksStateId,
		private int $logStateId,
		private int $fenceStateId,
		private int $chestStateId
	){}

	public function canPlace(GenerationVolume $volume, int $x, int $y, int $z) : bool{
		return ($y - 1) > $volume->getMinY() && ($y + 7) < $volume->getMaxY();
	}

	public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		$this->originX = $x;
		$this->originY = $y;
		$this->originZ = $z;

		$c = self::CENTER;
		//hull: a 9x3 boat. Bottom (dy=0) planked; sides (b=+/-1) and ends (a=+/-4) walled dy=1..2; the hold (b=0
		//interior) is left open. A fixed rule punches "wreck" gaps in the planking.
		for($a = -4; $a <= 4; ++$a){
			for($b = -1; $b <= 1; ++$b){
				$this->plank($volume, $c + $a, 0, $b); //hull bottom
				$side = abs($b) === 1 || abs($a) === 4;
				for($dy = 1; $dy <= 2; ++$dy){
					if($side){
						$this->plank($volume, $c + $a, $dy, $b);
					}
				}
			}
		}
		//partial deck over the middle of the hull
		for($a = -3; $a <= 3; ++$a){
			for($b = -1; $b <= 1; ++$b){
				$this->plank($volume, $c + $a, 3, $b);
			}
		}
		//mast and a little rigging
		for($dy = 3; $dy <= 5; ++$dy){
			$this->set($volume, $c, $dy, 0, $this->logStateId);
		}
		$this->set($volume, $c, 6, 0, $this->fenceStateId);
		//two loot chests in the hold
		foreach(self::CHEST_OFFSETS as [$cdx, $cdy, $cdz]){
			$this->set($volume, $cdx, $cdy, $cdz, $this->chestStateId);
		}
	}

	private function plank(GenerationVolume $volume, int $dx, int $dy, int $dz) : void{
		//a fixed positional rule leaves gaps in the planking for the wrecked look (no Random)
		if((($dx * 2 + $dy * 3 + $dz) % 7) === 0){
			return;
		}
		$this->set($volume, $dx, $dy, $dz, $this->planksStateId);
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
