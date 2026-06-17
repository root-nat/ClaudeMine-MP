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
 * An igloo: a snow hut with an ice window, hiding a small stone-brick basement laboratory (a brewing stand and a loot
 * chest, filled by {@link OverworldStructureFurnisher}) reached through a hole in the floor.
 *
 * Built OFFSET from the anchor so the anchor column (0,0) stays open snowy ground for a stable surface scan. Geometry is
 * fully fixed; the basement's weathered stone-brick speckle is a fixed positional rule (no Random).
 */
final class IglooStructure extends Structure{

	public const MAX_RADIUS = 8;
	public const SALT = 0x5a1708;
	public const RARITY = 30;

	private const CENTER = 4; //hut centre X offset from the anchor; (0,0) stays open ground

	/** @var array{int, int, int} anchor-relative loot chest position (in the basement) */
	public const CHEST_OFFSET = [self::CENTER - 1, -2, 0];

	private int $originX = 0;
	private int $originY = 0;
	private int $originZ = 0;

	public function __construct(
		private int $airStateId,
		private int $snowStateId,
		private int $iceStateId,
		private int $stoneBricksStateId,
		private int $mossyStoneBricksStateId,
		private int $crackedStoneBricksStateId,
		private int $brewingStandStateId,
		private int $chestStateId
	){}

	public function canPlace(GenerationVolume $volume, int $x, int $y, int $z) : bool{
		return ($y - 4) > $volume->getMinY() && ($y + 4) < $volume->getMaxY();
	}

	public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		$this->originX = $x;
		$this->originY = $y;
		$this->originZ = $z;

		$c = self::CENTER;
		//snow hut: floor, 2-tall walls (with one ice window), flat snow roof; hollow interior
		for($a = -2; $a <= 2; ++$a){
			for($b = -2; $b <= 2; ++$b){
				$onWall = abs($a) === 2 || abs($b) === 2;
				$this->set($volume, $c + $a, 0, $b, $this->snowStateId); //hut floor (= basement ceiling)
				$this->set($volume, $c + $a, 3, $b, $this->snowStateId); //roof
				for($dy = 1; $dy <= 2; ++$dy){
					$this->set($volume, $c + $a, $dy, $b, $onWall ? $this->snowStateId : $this->airStateId);
				}
			}
		}
		//an ice window in the +X wall and a doorway in the -X wall
		$this->set($volume, $c + 2, 1, 0, $this->iceStateId);
		$this->set($volume, $c - 2, 1, 0, $this->airStateId);
		$this->set($volume, $c - 2, 2, 0, $this->airStateId);
		//hole in the hut floor down into the basement
		$this->set($volume, $c, 0, 0, $this->airStateId);

		//basement: a 5x5 stone-brick room (3x3 interior) under the hut, weathered with mossy/cracked variants
		for($a = -2; $a <= 2; ++$a){
			for($b = -2; $b <= 2; ++$b){
				$onWall = abs($a) === 2 || abs($b) === 2;
				$this->set($volume, $c + $a, -3, $b, $this->brick($a, $b, -3)); //floor
				for($dy = -2; $dy <= -1; ++$dy){
					$this->set($volume, $c + $a, $dy, $b, $onWall ? $this->brick($a, $b, $dy) : $this->airStateId);
				}
			}
		}
		//lab furnishings: a brewing stand and the loot chest
		$this->set($volume, $c + 1, -2, 1, $this->brewingStandStateId);
		[$cdx, $cdy, $cdz] = self::CHEST_OFFSET;
		$this->set($volume, $cdx, $cdy, $cdz, $this->chestStateId);
	}

	private function brick(int $a, int $b, int $dy) : int{
		return match((($a * 5 + $b * 3 + $dy) & 7)){
			0 => $this->mossyStoneBricksStateId,
			1 => $this->crackedStoneBricksStateId,
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
