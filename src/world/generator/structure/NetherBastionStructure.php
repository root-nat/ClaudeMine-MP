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

use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\generator\carver\GenerationVolume;
use function abs;

/**
 * A nether bastion remnant in the vanilla spirit: an open blackstone courtyard ringed by crenellated ramparts (a mix of
 * blackstone, polished blackstone, polished-blackstone bricks, cracked bricks and gilded blackstone, capped with merlons
 * and polished-brick slab battlements), standing over the lava seas on descending polished-basalt stilts, with a walled
 * and roofed treasure chamber tucked into one corner holding a gold-and-magma pile and a loot chest.
 *
 * Block placement here is async (block states only). The loot chest's contents and the guarding piglins are filled in on
 * the main thread by {@link NetherBastionFurnisher}, which re-derives the same anchor and reads {@link self::CHEST_OFFSET}
 * / {@link self::PIGLIN_OFFSETS} - these are FIXED offsets from the anchor (never randomised), so the furnisher needs only
 * the anchor to land exactly on the placed chest block and the open courtyard floor.
 *
 * The layout is a pure function of the anchor (only cosmetic speckle uses the Random, and its draw sequence is identical
 * in every chunk because the loops always run in full), and every write is clamped to {@link self::MAX_RADIUS} of the
 * anchor so the populator's chunk scan radius covers the whole footprint.
 */
final class NetherBastionStructure extends Structure{

	/** Maximum block distance any part of the bastion may reach from its anchor, on the X/Z axes. */
	public const MAX_RADIUS = 14;

	/** @var array{int, int, int} anchor-relative offset of the treasure chest block (centre of the treasure chamber) */
	public const CHEST_OFFSET = [5, 1, 5];
	/** @var list<array{int, int, int}> anchor-relative offsets of the open courtyard cells where piglins are spawned */
	public const PIGLIN_OFFSETS = [[0, 1, 0], [3, 1, -3], [-3, 1, 3], [-4, 1, -4]];

	private const COURT_HALF = 7;
	private const WALL_HEIGHT = 4;
	private const SUPPORT_DEPTH = 6;
	/** Half-extent of the treasure chamber footprint, centred on the chest. */
	private const TREASURE_HALF = 2;

	private int $originX = 0;
	private int $originY = 0;
	private int $originZ = 0;

	/**
	 * @param array<int, int> $stairStateIds polished-blackstone-brick stair state ids keyed by Facing (bottom half)
	 */
	public function __construct(
		private int $airStateId,
		private int $blackstoneStateId,
		private int $polishedStateId,
		private int $polishedBricksStateId,
		private int $crackedStateId,
		private int $chiseledStateId,
		private int $gildedStateId,
		private int $basaltStateId,
		private int $goldBlockStateId,
		private int $magmaStateId,
		private int $chestStateId,
		private array $stairStateIds,
		private int $slabTopStateId
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
		$this->buildTreasureChamber($volume);
	}

	private function buildCourtyard(GenerationVolume $volume, Random $random) : void{
		//open polished floor, cleared to head height, speckled with gilded and cracked variants for a weathered look
		for($dx = -self::COURT_HALF; $dx <= self::COURT_HALF; ++$dx){
			for($dz = -self::COURT_HALF; $dz <= self::COURT_HALF; ++$dz){
				$roll = $random->nextBoundedInt(16);
				$this->set($volume, $dx, 0, $dz, match(true){
					$roll === 0 => $this->gildedStateId,
					$roll < 3 => $this->crackedStateId,
					$roll < 6 => $this->polishedStateId,
					default => $this->polishedBricksStateId
				});
				for($dy = 1; $dy <= self::WALL_HEIGHT + 1; ++$dy){
					$this->set($volume, $dx, $dy, $dz, $this->airStateId);
				}
			}
		}
	}

	private function buildRamparts(GenerationVolume $volume, Random $random) : void{
		for($i = -self::COURT_HALF; $i <= self::COURT_HALF; ++$i){
			foreach([[-self::COURT_HALF, $i], [self::COURT_HALF, $i], [$i, -self::COURT_HALF], [$i, self::COURT_HALF]] as [$dx, $dz]){
				for($dy = 1; $dy <= self::WALL_HEIGHT; ++$dy){
					$roll = $random->nextBoundedInt(12);
					$this->set($volume, $dx, $dy, $dz, match(true){
						$roll === 0 => $this->gildedStateId,
						$roll < 3 => $this->polishedBricksStateId,
						$roll < 5 => $this->polishedStateId,
						$roll < 6 => $this->crackedStateId,
						default => $this->blackstoneStateId
					});
				}
				//crenellated battlements: blackstone merlons every other cell, polished-brick slab caps between them
				$this->set($volume, $dx, self::WALL_HEIGHT + 1, $dz, ($i & 1) === 0 ? $this->blackstoneStateId : $this->slabTopStateId);
			}
		}
	}

	private function buildSupports(GenerationVolume $volume) : void{
		//polished-basalt stilts driving down into the lava seas at the four corners
		foreach([[-self::COURT_HALF, -self::COURT_HALF], [-self::COURT_HALF, self::COURT_HALF], [self::COURT_HALF, -self::COURT_HALF], [self::COURT_HALF, self::COURT_HALF]] as [$dx, $dz]){
			for($dy = 1; $dy <= self::SUPPORT_DEPTH; ++$dy){
				$this->set($volume, $dx, -$dy, $dz, $this->basaltStateId);
			}
		}
	}

	private function buildTreasureChamber(GenerationVolume $volume) : void{
		[$cx, $cy, $cz] = self::CHEST_OFFSET;
		$h = self::TREASURE_HALF;

		//a walled, roofed chamber centred on the chest; chiseled corner posts, polished-brick walls and roof
		for($dx = -$h; $dx <= $h; ++$dx){
			for($dz = -$h; $dz <= $h; ++$dz){
				$x = $cx + $dx;
				$z = $cz + $dz;
				$onWall = abs($dx) === $h || abs($dz) === $h;
				$corner = abs($dx) === $h && abs($dz) === $h;
				$this->set($volume, $x, $cy + 3, $z, $this->polishedBricksStateId); //roof
				if($onWall){
					for($dy = 0; $dy <= 2; ++$dy){
						$this->set($volume, $x, $cy + $dy, $z, $corner ? $this->chiseledStateId : $this->polishedBricksStateId);
					}
				}else{
					//interior: gold floor with magma at the inner corners, cleared above for the chest
					$this->set($volume, $x, $cy - 1, $z, abs($dx) === 1 && abs($dz) === 1 ? $this->magmaStateId : $this->goldBlockStateId);
					for($dy = 0; $dy <= 2; ++$dy){
						$this->set($volume, $x, $cy + $dy, $z, $this->airStateId);
					}
				}
			}
		}
		//doorway toward the courtyard centre (the -X wall), 2 tall, framed with brick stairs
		$this->set($volume, $cx - $h, $cy, $cz, $this->airStateId);
		$this->set($volume, $cx - $h, $cy + 1, $cz, $this->airStateId);
		$this->setStair($volume, $cx - $h, $cy, $cz - 1, Facing::EAST);
		$this->setStair($volume, $cx - $h, $cy, $cz + 1, Facing::EAST);

		//the loot chest on the gold pile at the centre
		$this->set($volume, $cx, $cy, $cz, $this->chestStateId);
	}

	private function setStair(GenerationVolume $volume, int $dx, int $dy, int $dz, int $facing) : void{
		$this->set($volume, $dx, $dy, $dz, $this->stairStateIds[$facing] ?? $this->polishedBricksStateId);
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
