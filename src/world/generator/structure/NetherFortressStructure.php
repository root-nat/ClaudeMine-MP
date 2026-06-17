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
 * A nether-brick fortress in the vanilla spirit: it splits into two families that meet at a thick 7x7 central junction.
 * The OPEN family is the iconic skybound mazework - 3-wide arched bridges (fence railings, two-course stone arches and
 * support pillars descending into the lava seas), a secondary bridge crossing with a perpendicular spur, and an open,
 * fenced, stepped blaze-spawner arena. The ENCLOSED family is the covered half - a barred-window nether-brick corridor
 * leading to a stepped nether-wart staircase room where the wart climbs soul-sand treads.
 *
 * The whole layout is a pure function of the anchor and the region {@link Random}: it never reads the volume to decide
 * geometry, so every chunk the fortress spills into re-derives the identical structure and writes only its own clipped
 * slice (out-of-bounds writes are ignored). All Random draws are made up-front and unconditionally, so the draw sequence
 * is identical no matter which chunk is being populated. Every write stays within {@link self::MAX_RADIUS} of the anchor
 * so the populator's chunk scan radius is guaranteed to cover the whole footprint.
 *
 * Geometry only: the blaze spawner is placed as a bare block state. The {@link \pocketmine\block\MonsterSpawner} block
 * configures its own tile (a blaze, in the Nether) the first time it ticks, so no main-thread furnisher is needed.
 */
final class NetherFortressStructure extends Structure{

	/** Maximum block distance any part of the fortress may reach from its anchor, on the X/Z axes. */
	public const MAX_RADIUS = 34;

	private const SUPPORT_DEPTH = 6;
	private const ARCH_SPACING = 5;
	private const JUNCTION_HALF = 3; //7x7 junction
	/** Forward offset along the long arm at which the secondary bridge crossing sits (always < the minimum arm length). */
	private const CROSSING_OFFSET = 8;

	private int $originX = 0;
	private int $originY = 0;
	private int $originZ = 0;

	/**
	 * @param array<int, int> $stairStateIds         nether brick stair state ids keyed by Facing (bottom half)
	 * @param array<int, int> $stairUpsideDownStateIds nether brick stair state ids keyed by Facing (top/upside-down half)
	 */
	public function __construct(
		private int $airStateId,
		private int $netherBricksStateId,
		private int $fenceStateId,
		private int $soulSandStateId,
		private int $netherWartStateId,
		private int $spawnerStateId,
		private array $stairStateIds,
		private array $stairUpsideDownStateIds
	){}

	public function canPlace(GenerationVolume $volume, int $x, int $y, int $z) : bool{
		//depends only on the anchor Y and the world's (global) height bounds, so it is identical for every chunk that
		//re-derives this fortress; the deepest pillars reach -SUPPORT_DEPTH and the wart room ceiling reaches +8
		return ($y - self::SUPPORT_DEPTH) > $volume->getMinY()
			&& ($y + 8) < $volume->getMaxY();
	}

	public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		$this->originX = $x;
		$this->originY = $y;
		$this->originZ = $z;

		//all Random draws up-front and unconditional, so the sequence is identical in every chunk that re-derives this
		//fortress and the geometry never diverges across a chunk boundary
		$mainAxisX = $random->nextBoolean();
		$armA = 12 + $random->nextBoundedInt(9); //12..20 - carries the blaze arena and the secondary crossing
		$armB = 12 + $random->nextBoundedInt(9); //12..20 - a railed dead-end bridge
		$branchLen = 8 + $random->nextBoundedInt(5); //8..12 - the enclosed corridor to the wart room
		$branchPositive = $random->nextBoolean();
		$spurLen = 6 + $random->nextBoundedInt(5); //6..10 - the secondary crossing's perpendicular spur
		$spurPositive = $random->nextBoolean();

		if($mainAxisX){
			$dirA = Facing::EAST;
			$dirB = Facing::WEST;
			$dirBranch = $branchPositive ? Facing::SOUTH : Facing::NORTH;
		}else{
			$dirA = Facing::SOUTH;
			$dirB = Facing::NORTH;
			$dirBranch = $branchPositive ? Facing::EAST : Facing::WEST;
		}

		$this->buildJunction($volume, [$dirA, $dirB, $dirBranch]);
		$this->buildBridge($volume, $dirA, $armA, false); //connects to the arena, so its far end is left open
		$this->buildBridge($volume, $dirB, $armB, true); //railed dead-end terminus
		$this->buildSecondaryCrossing($volume, $dirA, $spurLen, $spurPositive);
		$this->buildBlazeArena($volume, $dirA, $armA);
		$this->buildCorridor($volume, $dirBranch, $branchLen);
		$this->buildWartStaircaseRoom($volume, $dirBranch, $branchLen);
	}

	/**
	 * @return array{int, int, int, int} [forwardDX, forwardDZ, perpDX, perpDZ] for a horizontal facing
	 */
	private static function axes(int $facing) : array{
		return match($facing){
			Facing::EAST => [1, 0, 0, 1],
			Facing::WEST => [-1, 0, 0, 1],
			Facing::SOUTH => [0, 1, 1, 0],
			Facing::NORTH => [0, -1, 1, 0],
			default => [1, 0, 0, 1]
		};
	}

	private static function facingFor(int $dx, int $dz) : int{
		if($dx > 0){
			return Facing::EAST;
		}
		if($dx < 0){
			return Facing::WEST;
		}
		if($dz > 0){
			return Facing::SOUTH;
		}
		return Facing::NORTH;
	}

	/**
	 * @param int[] $usedFacings the facings on which a bridge or corridor attaches (their rim cells are left open)
	 */
	private function buildJunction(GenerationVolume $volume, array $usedFacings) : void{
		$h = self::JUNCTION_HALF;
		//7x7 deck, open to head height
		for($dx = -$h; $dx <= $h; ++$dx){
			for($dz = -$h; $dz <= $h; ++$dz){
				$this->set($volume, $dx, 0, $dz, $this->netherBricksStateId);
				for($dy = 1; $dy <= 3; ++$dy){
					$this->set($volume, $dx, $dy, $dz, $this->airStateId);
				}
			}
		}
		//corner pillar bundles diving into the void
		foreach([[-$h, -$h], [-$h, $h], [$h, -$h], [$h, $h]] as [$cx, $cz]){
			for($dy = 1; $dy <= self::SUPPORT_DEPTH; ++$dy){
				$this->set($volume, $cx, -$dy, $cz, $this->netherBricksStateId);
			}
		}
		for($dx = -$h; $dx <= $h; ++$dx){
			for($dz = -$h; $dz <= $h; ++$dz){
				$onEdgeX = abs($dx) === $h;
				$onEdgeZ = abs($dz) === $h;
				if(!$onEdgeX && !$onEdgeZ){
					continue;
				}
				//corbel ring: a thickened underside lip of upside-down stairs along the non-corner perimeter, facing inward
				if($onEdgeX !== $onEdgeZ){
					$inDx = $onEdgeX ? -($dx <=> 0) : 0;
					$inDz = $onEdgeZ ? -($dz <=> 0) : 0;
					$this->setStair($volume, $dx, -1, $dz, self::facingFor($inDx, $inDz), true);
				}
				//rim parapet, leaving a 3-wide mouth open on each edge a bridge/corridor attaches to
				if(!$this->isBridgeMouth($dx, $dz, $usedFacings)){
					$this->set($volume, $dx, 1, $dz, $this->fenceStateId);
				}
			}
		}
	}

	/**
	 * @param int[] $usedFacings
	 */
	private function isBridgeMouth(int $dx, int $dz, array $usedFacings) : bool{
		foreach($usedFacings as $facing){
			[$fdx, $fdz, $pdx, $pdz] = self::axes($facing);
			if(($dx * $fdx + $dz * $fdz) === self::JUNCTION_HALF && abs($dx * $pdx + $dz * $pdz) <= 1){
				return true;
			}
		}
		return false;
	}

	private function buildBridge(GenerationVolume $volume, int $facing, int $length, bool $capEnd) : void{
		[$fdx, $fdz, $pdx, $pdz] = self::axes($facing);
		//start at 4 so the deck connects seamlessly to the junction rim (which reaches forward offset 3)
		for($s = 4; $s <= $length; ++$s){
			for($p = -1; $p <= 1; ++$p){
				$dx = $fdx * $s + $pdx * $p;
				$dz = $fdz * $s + $pdz * $p;
				$this->set($volume, $dx, 0, $dz, $this->netherBricksStateId);
				for($dy = 1; $dy <= 3; ++$dy){
					$this->set($volume, $dx, $dy, $dz, $this->airStateId);
				}
			}
			//fence railings on the two outer edges
			$this->set($volume, $fdx * $s + $pdx * -1, 1, $fdz * $s + $pdz * -1, $this->fenceStateId);
			$this->set($volume, $fdx * $s + $pdx * 1, 1, $fdz * $s + $pdz * 1, $this->fenceStateId);

			if($s % self::ARCH_SPACING === 0){
				$this->buildArch($volume, $facing, $s);
			}
		}
		if($capEnd){
			//rail the far end so the dead-end bridge isn't an unguarded drop
			for($p = -1; $p <= 1; ++$p){
				$this->set($volume, $fdx * $length + $pdx * $p, 1, $fdz * $length + $pdz * $p, $this->fenceStateId);
			}
		}
	}

	private function buildArch(GenerationVolume $volume, int $facing, int $s) : void{
		[$fdx, $fdz, $pdx, $pdz] = self::axes($facing);
		$facingFromNeg = $pdz !== 0 ? Facing::SOUTH : Facing::EAST; //inward from the perp -1 column
		$facingFromPos = $pdz !== 0 ? Facing::NORTH : Facing::WEST; //inward from the perp +1 column

		//two-course arch haunches just under the deck edges, then support columns descending toward the lava
		$this->setStair($volume, $fdx * $s + $pdx * -1, -1, $fdz * $s + $pdz * -1, $facingFromNeg, true);
		$this->setStair($volume, $fdx * $s + $pdx * 1, -1, $fdz * $s + $pdz * 1, $facingFromPos, true);
		//thickened underside band under the centre of the deck
		$this->set($volume, $fdx * $s, -1, $fdz * $s, $this->netherBricksStateId);
		for($depth = 2; $depth <= self::SUPPORT_DEPTH; ++$depth){
			$this->set($volume, $fdx * $s + $pdx * -1, -$depth, $fdz * $s + $pdz * -1, $this->netherBricksStateId);
			$this->set($volume, $fdx * $s + $pdx * 1, -$depth, $fdz * $s + $pdz * 1, $this->netherBricksStateId);
		}
	}

	private function buildSecondaryCrossing(GenerationVolume $volume, int $facing, int $spurLen, bool $spurPositive) : void{
		[$fdx, $fdz, $pdx, $pdz] = self::axes($facing);
		$c = self::CROSSING_OFFSET;
		$spurSign = $spurPositive ? 1 : -1;

		//5x5 pad over the long arm, open to head height, with corner pillars
		for($a = -2; $a <= 2; ++$a){
			for($b = -2; $b <= 2; ++$b){
				$dx = $fdx * ($c + $a) + $pdx * $b;
				$dz = $fdz * ($c + $a) + $pdz * $b;
				$this->set($volume, $dx, 0, $dz, $this->netherBricksStateId);
				for($dy = 1; $dy <= 3; ++$dy){
					$this->set($volume, $dx, $dy, $dz, $this->airStateId);
				}
			}
		}
		foreach([[-2, -2], [-2, 2], [2, -2], [2, 2]] as [$a, $b]){
			$dx = $fdx * ($c + $a) + $pdx * $b;
			$dz = $fdz * ($c + $a) + $pdz * $b;
			for($dy = 1; $dy <= self::SUPPORT_DEPTH; ++$dy){
				$this->set($volume, $dx, -$dy, $dz, $this->netherBricksStateId);
			}
		}
		//rim fence: omit the through-bridge mouths (perp -1..1 on the two |a|=2 edges) and the spur mouth on the chosen side
		for($a = -2; $a <= 2; ++$a){
			for($b = -2; $b <= 2; ++$b){
				if(abs($a) !== 2 && abs($b) !== 2){
					continue;
				}
				$throughMouth = abs($a) === 2 && abs($b) <= 1;
				$spurMouth = $b === 2 * $spurSign && abs($a) <= 1;
				if($throughMouth || $spurMouth){
					continue;
				}
				$dx = $fdx * ($c + $a) + $pdx * $b;
				$dz = $fdz * ($c + $a) + $pdz * $b;
				$this->set($volume, $dx, 1, $dz, $this->fenceStateId);
			}
		}
		//perpendicular spur: a short railed bridge leaving the pad, 3 wide along the long-arm axis
		for($t = 3; $t <= $spurLen; ++$t){
			$bp = $spurSign * $t;
			for($u = -1; $u <= 1; ++$u){
				$dx = $fdx * ($c + $u) + $pdx * $bp;
				$dz = $fdz * ($c + $u) + $pdz * $bp;
				$this->set($volume, $dx, 0, $dz, $this->netherBricksStateId);
				for($dy = 1; $dy <= 3; ++$dy){
					$this->set($volume, $dx, $dy, $dz, $this->airStateId);
				}
			}
			$this->set($volume, $fdx * ($c - 1) + $pdx * $bp, 1, $fdz * ($c - 1) + $pdz * $bp, $this->fenceStateId);
			$this->set($volume, $fdx * ($c + 1) + $pdx * $bp, 1, $fdz * ($c + 1) + $pdz * $bp, $this->fenceStateId);
		}
		//cap the spur end
		for($u = -1; $u <= 1; ++$u){
			$this->set($volume, $fdx * ($c + $u) + $pdx * ($spurSign * $spurLen), 1, $fdz * ($c + $u) + $pdz * ($spurSign * $spurLen), $this->fenceStateId);
		}
	}

	private function buildBlazeArena(GenerationVolume $volume, int $facing, int $armLen) : void{
		[$fdx, $fdz, $pdx, $pdz] = self::axes($facing);
		$cf = $armLen + 4; //forward offset of the arena centre, just past the bridge end

		//open 7x7 platform floor, cleared to the sky (no ceiling)
		for($a = -3; $a <= 3; ++$a){
			for($b = -3; $b <= 3; ++$b){
				$dx = $fdx * ($cf + $a) + $pdx * $b;
				$dz = $fdz * ($cf + $a) + $pdz * $b;
				$this->set($volume, $dx, 0, $dz, $this->netherBricksStateId);
				for($dy = 1; $dy <= 5; ++$dy){
					$this->set($volume, $dx, $dy, $dz, $this->airStateId);
				}
			}
		}
		//corner pillars
		foreach([[-3, -3], [-3, 3], [3, -3], [3, 3]] as [$a, $b]){
			$dx = $fdx * ($cf + $a) + $pdx * $b;
			$dz = $fdz * ($cf + $a) + $pdz * $b;
			for($dy = 1; $dy <= self::SUPPORT_DEPTH; ++$dy){
				$this->set($volume, $dx, -$dy, $dz, $this->netherBricksStateId);
			}
		}
		//stepped pyramid: a ring of stairs at dy=1 facing outward, an inner raised base, then a central pedestal
		for($a = -2; $a <= 2; ++$a){
			for($b = -2; $b <= 2; ++$b){
				$dx = $fdx * ($cf + $a) + $pdx * $b;
				$dz = $fdz * ($cf + $a) + $pdz * $b;
				if(abs($a) === 2 || abs($b) === 2){
					$outX = abs($a) >= abs($b) ? ($a <=> 0) : 0;
					$outB = abs($a) >= abs($b) ? 0 : ($b <=> 0);
					$this->setStair($volume, $dx, 1, $dz, self::facingFor($fdx * $outX + $pdx * $outB, $fdz * $outX + $pdz * $outB), false);
				}else{
					$this->set($volume, $dx, 1, $dz, $this->netherBricksStateId);
				}
			}
		}
		//central pedestal carrying the blaze spawner (placed as a bare state; the block self-configures to a blaze in the Nether)
		$this->set($volume, $fdx * $cf, 2, $fdz * $cf, $this->netherBricksStateId);
		$this->set($volume, $fdx * $cf, 3, $fdz * $cf, $this->spawnerStateId);
		//fenced parapet around the rim, with the bridge mouth left open on the junction-facing edge
		for($a = -3; $a <= 3; ++$a){
			for($b = -3; $b <= 3; ++$b){
				if(abs($a) !== 3 && abs($b) !== 3){
					continue;
				}
				if($a === -3 && abs($b) <= 1){
					continue; //bridge mouth
				}
				$dx = $fdx * ($cf + $a) + $pdx * $b;
				$dz = $fdz * ($cf + $a) + $pdz * $b;
				$this->set($volume, $dx, 2, $dz, $this->fenceStateId);
			}
		}
	}

	private function buildCorridor(GenerationVolume $volume, int $facing, int $length) : void{
		[$fdx, $fdz, $pdx, $pdz] = self::axes($facing);
		//5-wide enclosed corridor (walls at perp +/-2, 3-wide interior), 3 tall, with barred fence windows
		for($s = 4; $s <= $length; ++$s){
			$isWindow = ($s % 3) === 0;
			for($p = -2; $p <= 2; ++$p){
				$dx = $fdx * $s + $pdx * $p;
				$dz = $fdz * $s + $pdz * $p;
				$this->set($volume, $dx, 0, $dz, $this->netherBricksStateId); //floor
				$this->set($volume, $dx, 4, $dz, $this->netherBricksStateId); //ceiling
				if(abs($p) === 2){
					if($isWindow){
						$this->set($volume, $dx, 1, $dz, $this->netherBricksStateId);
						$this->set($volume, $dx, 2, $dz, $this->fenceStateId); //barred window
						$this->set($volume, $dx, 3, $dz, $this->netherBricksStateId);
					}else{
						for($dy = 1; $dy <= 3; ++$dy){
							$this->set($volume, $dx, $dy, $dz, $this->netherBricksStateId);
						}
					}
				}else{
					for($dy = 1; $dy <= 3; ++$dy){
						$this->set($volume, $dx, $dy, $dz, $this->airStateId); //interior
					}
				}
			}
		}
		//under-floor support pillars at the corridor's four corners
		foreach([4, $length] as $s){
			foreach([-2, 2] as $p){
				$dx = $fdx * $s + $pdx * $p;
				$dz = $fdz * $s + $pdz * $p;
				for($dy = 1; $dy <= 4; ++$dy){
					$this->set($volume, $dx, -$dy, $dz, $this->netherBricksStateId);
				}
			}
		}
	}

	private function buildWartStaircaseRoom(GenerationVolume $volume, int $facing, int $branchLen) : void{
		[$fdx, $fdz, $pdx, $pdz] = self::axes($facing);
		$cf = $branchLen + 4; //forward offset of the room centre, just past the corridor end
		$ceilingY = 7;

		//enclosed 7x7 shell: floor, perimeter walls and a raised ceiling that clears the climbing wart
		for($a = -3; $a <= 3; ++$a){
			for($b = -3; $b <= 3; ++$b){
				$dx = $fdx * ($cf + $a) + $pdx * $b;
				$dz = $fdz * ($cf + $a) + $pdz * $b;
				$onWall = abs($a) === 3 || abs($b) === 3;
				$this->set($volume, $dx, 0, $dz, $this->netherBricksStateId);
				$this->set($volume, $dx, $ceilingY, $dz, $this->netherBricksStateId);
				for($dy = 1; $dy < $ceilingY; ++$dy){
					$this->set($volume, $dx, $dy, $dz, $onWall ? $this->netherBricksStateId : $this->airStateId);
				}
			}
		}
		//ascending soul-sand staircase: four full-width treads climbing from the entrance toward the back wall, each
		//bearing a strip of nether wart - the iconic vanilla growing-wart flight
		for($i = 0; $i <= 3; ++$i){
			$a = -2 + $i;
			for($b = -2; $b <= 2; ++$b){
				$dx = $fdx * ($cf + $a) + $pdx * $b;
				$dz = $fdz * ($cf + $a) + $pdz * $b;
				for($fy = 0; $fy < $i; ++$fy){
					$this->set($volume, $dx, $fy, $dz, $this->netherBricksStateId); //riser mass
				}
				$this->set($volume, $dx, $i, $dz, $this->soulSandStateId);
				$this->set($volume, $dx, $i + 1, $dz, $this->netherWartStateId);
			}
		}
		//barred doorway in the entrance wall (facing the corridor), 2 tall
		for($b = -1; $b <= 1; ++$b){
			$dx = $fdx * ($cf - 3) + $pdx * $b;
			$dz = $fdz * ($cf - 3) + $pdz * $b;
			$this->set($volume, $dx, 1, $dz, $this->airStateId);
			$this->set($volume, $dx, 2, $dz, $this->airStateId);
		}
	}

	private function setStair(GenerationVolume $volume, int $dx, int $dy, int $dz, int $facing, bool $upsideDown) : void{
		$palette = $upsideDown ? $this->stairUpsideDownStateIds : $this->stairStateIds;
		$this->set($volume, $dx, $dy, $dz, $palette[$facing] ?? $this->netherBricksStateId);
	}

	private function set(GenerationVolume $volume, int $dx, int $dy, int $dz, int $stateId) : void{
		//the radius clamp keeps the whole structure inside MAX_RADIUS so the populator's scan radius covers it; isInBounds
		//then clips to the chunks actually loaded in this populate pass
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
