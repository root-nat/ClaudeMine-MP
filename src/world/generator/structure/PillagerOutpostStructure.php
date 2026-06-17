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
 * A pillager outpost: a tall dark-oak watchtower on a cobblestone base, with a ladder shaft, an open fenced lookout
 * platform, a loot chest and a couple of pillagers (spawned by {@link OverworldStructureFurnisher}).
 *
 * Built OFFSET to one side of the anchor so the anchor column (0,0) stays open ground (stable surface scan). Geometry is
 * fully fixed (no Random). The chest and pillager points are FIXED anchor-relative offsets for the furnisher.
 */
final class PillagerOutpostStructure extends Structure{

	public const MAX_RADIUS = 7;
	public const SALT = 0x5a1706;
	public const RARITY = 32;

	private const CENTER = 4; //tower centre X offset from the anchor; (0,0) stays open ground
	private const PLATFORM_Y = 15;

	/** @var array{int, int, int} anchor-relative loot chest position (platform centre) */
	public const CHEST_OFFSET = [self::CENTER, self::PLATFORM_Y + 1, 0];
	/** @var list<array{int, int, int}> anchor-relative pillager stand points on the platform */
	public const MOB_OFFSETS = [[self::CENTER + 1, self::PLATFORM_Y + 1, 1], [self::CENTER - 1, self::PLATFORM_Y + 1, -1]];

	private int $originX = 0;
	private int $originY = 0;
	private int $originZ = 0;

	public function __construct(
		private int $airStateId,
		private int $logStateId,
		private int $planksStateId,
		private int $fenceStateId,
		private int $cobblestoneStateId,
		private int $ladderStateId,
		private int $chestStateId
	){}

	public function canPlace(GenerationVolume $volume, int $x, int $y, int $z) : bool{
		return $volume->isInBounds($x, $y, $z) && ($y + self::PLATFORM_Y + 3) < $volume->getMaxY();
	}

	public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		$this->originX = $x;
		$this->originY = $y;
		$this->originZ = $z;

		$c = self::CENTER;
		$top = self::PLATFORM_Y;

		//solid cobblestone base, then a hollow dark-oak shaft up to the platform
		for($a = -2; $a <= 2; ++$a){
			for($b = -2; $b <= 2; ++$b){
				$corner = abs($a) === 2 && abs($b) === 2;
				$onWall = abs($a) === 2 || abs($b) === 2;
				for($dy = 0; $dy < $top; ++$dy){
					if($dy <= 1){
						$this->set($volume, $c + $a, $dy, $b, $this->cobblestoneStateId); //foundation
					}elseif($corner){
						$this->set($volume, $c + $a, $dy, $b, $this->logStateId); //corner posts
					}elseif($onWall){
						$this->set($volume, $c + $a, $dy, $b, $this->planksStateId); //plank walls
					}else{
						$this->set($volume, $c + $a, $dy, $b, $this->airStateId); //hollow interior
					}
				}
			}
		}
		//ladder up the inner east wall
		for($dy = 2; $dy < $top; ++$dy){
			$this->set($volume, $c + 1, $dy, 0, $this->ladderStateId);
		}
		//platform floor, fence railing, cleared lookout interior and a roofed overhang
		for($a = -2; $a <= 2; ++$a){
			for($b = -2; $b <= 2; ++$b){
				$this->set($volume, $c + $a, $top, $b, $this->planksStateId);
				if(abs($a) === 2 || abs($b) === 2){
					$this->set($volume, $c + $a, $top + 1, $b, $this->fenceStateId);
				}else{
					//clear the lookout so it (and the pillagers) have headroom
					$this->set($volume, $c + $a, $top + 1, $b, $this->airStateId);
					$this->set($volume, $c + $a, $top + 2, $b, $this->airStateId);
				}
				$this->set($volume, $c + $a, $top + 3, $b, $this->planksStateId); //roof
			}
		}
		//corner posts holding up the roof
		foreach([[-2, -2], [-2, 2], [2, -2], [2, 2]] as [$a, $b]){
			$this->set($volume, $c + $a, $top + 2, $b, $this->logStateId);
		}
		//trapdoor-style hole in the platform floor at the ladder top, so the climb actually reaches the lookout
		$this->set($volume, $c + 1, $top, 0, $this->airStateId);
		//the loot chest at the platform centre
		$this->set($volume, $c, $top + 1, 0, $this->chestStateId);
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
