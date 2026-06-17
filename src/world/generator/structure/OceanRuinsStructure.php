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
 * Ocean ruins: a small ruined stone-brick building (sand-strewn, weathered with mossy/cracked variants) on the ocean
 * floor, holding a loot chest filled by {@link OverworldStructureFurnisher}.
 *
 * Built OFFSET from the anchor so the anchor column (0,0) stays open seabed (stable surface scan). Fully fixed geometry;
 * the ruin gaps and weathering are fixed positional rules.
 */
final class OceanRuinsStructure extends Structure{

	public const MAX_RADIUS = 7;
	public const SALT = 0x5a170d;
	public const RARITY = 26;

	private const CENTER = 4; //ruin centre X offset from the anchor; (0,0) stays open seabed

	/** @var array{int, int, int} anchor-relative loot chest position */
	public const CHEST_OFFSET = [self::CENTER, 1, 0];

	private int $originX = 0;
	private int $originY = 0;
	private int $originZ = 0;

	public function __construct(
		private int $stoneBricksStateId,
		private int $mossyStateId,
		private int $crackedStateId,
		private int $sandStateId,
		private int $chestStateId
	){}

	public function canPlace(GenerationVolume $volume, int $x, int $y, int $z) : bool{
		return $volume->isInBounds($x, $y, $z) && ($y + 4) < $volume->getMaxY();
	}

	public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		$this->originX = $x;
		$this->originY = $y;
		$this->originZ = $z;

		$c = self::CENTER;
		//stone-brick floor (sand-strewn), partial ruined walls; the building is 5x5
		for($a = -2; $a <= 2; ++$a){
			for($b = -2; $b <= 2; ++$b){
				//floor: mostly stone brick, the odd patch of sand drifted over it
				$this->set($volume, $c + $a, 0, $b, ((($a * 5 + $b) & 5) === 0) ? $this->sandStateId : $this->brick($a, $b, 0));
				//walls: only the perimeter, and only where the ruin rule keeps a block (gaps = collapsed)
				if(abs($a) === 2 || abs($b) === 2){
					for($dy = 1; $dy <= 2; ++$dy){
						if((($a * 3 + $b * 7 + $dy) % 4) !== 0){ //a gap where this is 0
							$this->set($volume, $c + $a, $dy, $b, $this->brick($a, $b, $dy));
						}
					}
				}
			}
		}
		//the loot chest on the floor at the centre
		[$cdx, $cdy, $cdz] = self::CHEST_OFFSET;
		$this->set($volume, $cdx, $cdy, $cdz, $this->chestStateId);
	}

	private function brick(int $a, int $b, int $dy) : int{
		return match((($a * 7 + $b * 3 + $dy) & 7)){
			0 => $this->mossyStateId,
			1 => $this->crackedStateId,
			default => $this->stoneBricksStateId
		};
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
