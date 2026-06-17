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
 * A desert pyramid (desert temple): a 21x21 stepped sandstone pyramid with the iconic orange/blue terracotta bullseye on
 * its hall floor, hiding a buried treasure chamber directly below - four loot chests around a stone pressure plate that
 * sits atop a TNT stack (the classic trap). The chest loot is filled main-thread by {@link OverworldStructureFurnisher}
 * from {@link self::CHEST_OFFSETS}.
 *
 * Anchored centred on (ax, groundY, az). The pyramid is HOLLOW with a 1-cell open shaft up its central axis (a skylight
 * at the apex), so the anchor column's top solid block is the terracotta floor at groundY both before and after building
 * - {@link SurfaceScan::topSolidY} therefore returns the same groundY from every chunk that re-derives the temple and
 * from the furnisher, keeping seams aligned and chest positions exact. Geometry is fully fixed (no Random draws).
 */
final class DesertTempleStructure extends Structure{

	public const MAX_RADIUS = 11;
	private const HALF = 10; //21x21 footprint
	private const HEIGHT = 9;

	//placement params shared by the populator (Normal.php) and the furnisher (OverworldStructureFurnisher), so the two
	//derive the SAME anchors and surface Y - never let these drift between the two call sites
	public const SALT = 0x5a1705;
	public const RARITY = 20;
	public const SURFACE_TOP_Y = 120;
	public const SURFACE_MIN_Y = 40;

	/** @var list<array{int, int, int}> anchor-relative offsets of the four treasure chests (read by the furnisher) */
	public const CHEST_OFFSETS = [[2, -4, 0], [-2, -4, 0], [0, -4, 2], [0, -4, -2]];

	private int $originX = 0;
	private int $originY = 0;
	private int $originZ = 0;

	public function __construct(
		private int $airStateId,
		private int $sandstoneStateId,
		private int $cutSandstoneStateId,
		private int $chiseledSandstoneStateId,
		private int $orangeTerracottaStateId,
		private int $blueTerracottaStateId,
		private int $pressurePlateStateId,
		private int $tntStateId,
		private int $chestStateId
	){}

	public function canPlace(GenerationVolume $volume, int $x, int $y, int $z) : bool{
		//room for the buried chamber (down to groundY-8) and the pyramid tip (up to groundY+9)
		return ($y - 9) > $volume->getMinY() && ($y + self::HEIGHT + 1) < $volume->getMaxY();
	}

	public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		$this->originX = $x;
		$this->originY = $y;
		$this->originZ = $z;

		$this->buildPyramid($volume);
		$this->buildFloorMotif($volume);
		$this->buildTreasureChamber($volume);
	}

	private function buildPyramid(GenerationVolume $volume) : void{
		//solid base platform at ground level
		for($dx = -self::HALF; $dx <= self::HALF; ++$dx){
			for($dz = -self::HALF; $dz <= self::HALF; ++$dz){
				$this->set($volume, $dx, 0, $dz, $this->sandstoneStateId);
			}
		}
		//hollow shrinking rings up to the tip; the centre column stays open (skylight), keeping the anchor scan stable
		for($level = 1; $level <= self::HEIGHT; ++$level){
			$r = self::HALF - $level; //9,8,...,1
			for($dx = -$r; $dx <= $r; ++$dx){
				for($dz = -$r; $dz <= $r; ++$dz){
					if(abs($dx) === $r || abs($dz) === $r){
						$this->set($volume, $dx, $level, $dz, $this->sandstoneStateId);
					}
				}
			}
		}
		//a 1-wide, 2-tall doorway through the front (-Z) base wall into the hollow hall
		$this->set($volume, 0, 1, -(self::HALF - 1), $this->airStateId);
		$this->set($volume, 0, 2, -(self::HALF - 1), $this->airStateId);
	}

	private function buildFloorMotif(GenerationVolume $volume) : void{
		//the iconic orange/blue bullseye on the hall floor, marking the treasure below
		for($dx = -2; $dx <= 2; ++$dx){
			for($dz = -2; $dz <= 2; ++$dz){
				$ring = abs($dx) === 2 || abs($dz) === 2;
				$inner = abs($dx) <= 1 && abs($dz) <= 1;
				$state = match(true){
					$dx === 0 && $dz === 0 => $this->orangeTerracottaStateId,
					$inner => $this->blueTerracottaStateId,
					$ring => $this->orangeTerracottaStateId,
					default => $this->sandstoneStateId
				};
				$this->set($volume, $dx, 0, $dz, $state);
			}
		}
	}

	private function buildTreasureChamber(GenerationVolume $volume) : void{
		//enclosed 7x7 shell (interior 5x5) hidden under the floor: cut-sandstone floor, walls and ceiling
		for($dx = -3; $dx <= 3; ++$dx){
			for($dz = -3; $dz <= 3; ++$dz){
				$onWall = abs($dx) === 3 || abs($dz) === 3;
				$this->set($volume, $dx, -5, $dz, $this->cutSandstoneStateId); //floor
				$this->set($volume, $dx, -1, $dz, $this->cutSandstoneStateId); //ceiling (under the hall floor at dy=0)
				$corner = abs($dx) === 3 && abs($dz) === 3;
				for($dy = -4; $dy <= -2; ++$dy){
					$this->set($volume, $dx, $dy, $dz, $onWall ? ($corner ? $this->chiseledSandstoneStateId : $this->cutSandstoneStateId) : $this->airStateId);
				}
			}
		}
		//central TNT trap: a pressure plate sitting on a stack of TNT sunk into the floor
		$this->set($volume, 0, -4, 0, $this->pressurePlateStateId);
		for($dy = -5; $dy >= -8; --$dy){
			$this->set($volume, 0, $dy, 0, $this->tntStateId);
		}
		//four loot chests around the plate (filled by the furnisher)
		foreach(self::CHEST_OFFSETS as [$cdx, $cdy, $cdz]){
			$this->set($volume, $cdx, $cdy, $cdz, $this->chestStateId);
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
