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
 * A nether bastion remnant: an open blackstone courtyard ringed by crenellated ramparts (speckled with gilded
 * blackstone), standing over the lava seas on descending corner supports, with a small gold-and-magma treasure holding a
 * loot chest.
 *
 * Block placement here is async (block states only). The loot chest's contents and the guarding piglins are filled in on
 * the main thread by {@link NetherBastionFurnisher}, which re-derives the same anchor and reads {@link self::CHEST_OFFSET}
 * / {@link self::PIGLIN_OFFSETS} - these are FIXED offsets from the anchor (never randomised), so the furnisher needs only
 * the anchor to land exactly on the placed chest block and the open courtyard floor.
 *
 * The layout is a pure function of the anchor (only cosmetic speckle uses the Random), and every write is clamped to
 * {@link self::MAX_RADIUS} of the anchor so the populator's chunk scan radius covers the whole footprint.
 */
final class NetherBastionStructure extends Structure{

	/** Maximum block distance any part of the bastion may reach from its anchor, on the X/Z axes. */
	public const MAX_RADIUS = 14;

	/** @var array{int, int, int} anchor-relative offset of the treasure chest block */
	public const CHEST_OFFSET = [5, 1, 5];
	/** @var list<array{int, int, int}> anchor-relative offsets of the open courtyard cells where piglins are spawned */
	public const PIGLIN_OFFSETS = [[0, 1, 0], [3, 1, -3], [-3, 1, 3], [-4, 1, -4]];

	private const COURT_HALF = 7;
	private const WALL_HEIGHT = 4;
	private const SUPPORT_DEPTH = 5;

	private int $originX = 0;
	private int $originY = 0;
	private int $originZ = 0;

	public function __construct(
		private int $airStateId,
		private int $blackstoneStateId,
		private int $polishedBricksStateId,
		private int $gildedStateId,
		private int $goldBlockStateId,
		private int $magmaStateId,
		private int $chestStateId
	){}

	public function canPlace(GenerationVolume $volume, int $x, int $y, int $z) : bool{
		//depends only on the anchor Y and the world's global height bounds, so it is identical for every chunk
		return ($y - self::SUPPORT_DEPTH) > $volume->getMinY()
			&& ($y + self::WALL_HEIGHT + 2) < $volume->getMaxY();
	}

	public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		$this->originX = $x;
		$this->originY = $y;
		$this->originZ = $z;

		$this->buildCourtyard($volume, $random);
		$this->buildRamparts($volume, $random);
		$this->buildSupports($volume);
		$this->buildTreasure($volume);
	}

	private function buildCourtyard(GenerationVolume $volume, Random $random) : void{
		//open polished-blackstone floor, cleared to head height, speckled with the odd gilded block
		for($dx = -self::COURT_HALF; $dx <= self::COURT_HALF; ++$dx){
			for($dz = -self::COURT_HALF; $dz <= self::COURT_HALF; ++$dz){
				$this->set($volume, $dx, 0, $dz, $random->nextBoundedInt(14) === 0 ? $this->gildedStateId : $this->polishedBricksStateId);
				for($dy = 1; $dy <= 5; ++$dy){
					$this->set($volume, $dx, $dy, $dz, $this->airStateId);
				}
			}
		}
	}

	private function buildRamparts(GenerationVolume $volume, Random $random) : void{
		for($i = -self::COURT_HALF; $i <= self::COURT_HALF; ++$i){
			foreach([[-self::COURT_HALF, $i], [self::COURT_HALF, $i], [$i, -self::COURT_HALF], [$i, self::COURT_HALF]] as [$dx, $dz]){
				for($dy = 1; $dy <= self::WALL_HEIGHT; ++$dy){
					$roll = $random->nextBoundedInt(10);
					$this->set($volume, $dx, $dy, $dz, $roll === 0 ? $this->gildedStateId : ($roll < 3 ? $this->polishedBricksStateId : $this->blackstoneStateId));
				}
				//crenellated merlons every other cell along the rim
				if(($i & 1) === 0){
					$this->set($volume, $dx, self::WALL_HEIGHT + 1, $dz, $this->blackstoneStateId);
				}
			}
		}
	}

	private function buildSupports(GenerationVolume $volume) : void{
		foreach([[-self::COURT_HALF, -self::COURT_HALF], [-self::COURT_HALF, self::COURT_HALF], [self::COURT_HALF, -self::COURT_HALF], [self::COURT_HALF, self::COURT_HALF]] as [$dx, $dz]){
			for($dy = 1; $dy <= self::SUPPORT_DEPTH; ++$dy){
				$this->set($volume, $dx, -$dy, $dz, $this->blackstoneStateId);
			}
		}
	}

	private function buildTreasure(GenerationVolume $volume) : void{
		[$cx, $cy, $cz] = self::CHEST_OFFSET;
		//a small gold pile with magma at the corners, the loot chest sitting on top
		for($dx = -1; $dx <= 1; ++$dx){
			for($dz = -1; $dz <= 1; ++$dz){
				$this->set($volume, $cx + $dx, 0, $cz + $dz, abs($dx) === 1 && abs($dz) === 1 ? $this->magmaStateId : $this->goldBlockStateId);
			}
		}
		$this->set($volume, $cx, $cy, $cz, $this->chestStateId);
	}

	private function set(GenerationVolume $volume, int $dx, int $dy, int $dz, int $stateId) : void{
		//the radius clamp keeps the bastion inside MAX_RADIUS so the populator's scan radius covers it; isInBounds then
		//clips to the chunks actually loaded in this populate pass
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
