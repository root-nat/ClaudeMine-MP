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
 * A nether-brick fortress: a central junction with arched open bridges (fence railings, supports descending into the
 * void), a blaze-spawner balcony at the end of the main span and an enclosed nether-wart room growing on soul sand.
 *
 * The whole layout is a pure function of the anchor and the region {@link Random}: it never reads the volume to decide
 * geometry, so every chunk the fortress spills into re-derives the identical structure and writes only its own clipped
 * slice (out-of-bounds writes are ignored). All writes stay within {@link self::MAX_RADIUS} of the anchor so the
 * populator's chunk scan radius is guaranteed to cover the whole footprint.
 *
 * Geometry only: the blaze spawner is placed as a block state; configuring its tile (the spawned mob) is a main-thread
 * step that cannot run in the async generator worker (see {@link Structure}).
 */
final class NetherFortressStructure extends Structure{

	/** Maximum block distance any part of the fortress may reach from its anchor, on the X/Z axes. */
	public const MAX_RADIUS = 30;

	private const SUPPORT_DEPTH = 5;
	private const ARCH_SPACING = 5;

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
		//re-derives this fortress
		return ($y - self::SUPPORT_DEPTH) > $volume->getMinY()
			&& ($y + 5) < $volume->getMaxY();
	}

	public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		$this->originX = $x;
		$this->originY = $y;
		$this->originZ = $z;

		$mainAxisX = $random->nextBoolean();
		$armA = 12 + $random->nextBoundedInt(9); //12..20 - carries the blaze balcony
		$armB = 12 + $random->nextBoundedInt(9); //12..20
		$branchLen = 8 + $random->nextBoundedInt(7); //8..14 - carries the wart room
		$branchPositive = $random->nextBoolean();

		if($mainAxisX){
			$dirA = Facing::EAST;
			$dirB = Facing::WEST;
			$dirBranch = $branchPositive ? Facing::SOUTH : Facing::NORTH;
		}else{
			$dirA = Facing::SOUTH;
			$dirB = Facing::NORTH;
			$dirBranch = $branchPositive ? Facing::EAST : Facing::WEST;
		}

		$this->buildJunction($volume);
		$this->buildBridge($volume, $dirA, $armA);
		$this->buildBridge($volume, $dirB, $armB);
		$this->buildBridge($volume, $dirBranch, $branchLen);
		$this->buildBalcony($volume, $dirA, $armA);
		$this->buildWartRoom($volume, $dirBranch, $branchLen);

		//the junction leaves all four rim openings clear for bridges, but only three are used; rail the fourth so it is
		//not an unguarded drop into the void
		[$ufx, $ufz] = self::axes(Facing::opposite($dirBranch));
		$this->set($volume, $ufx * 2, 1, $ufz * 2, $this->fenceStateId);
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

	private function buildJunction(GenerationVolume $volume) : void{
		//5x5 deck, open to head height, with corner supports descending into the void
		for($dx = -2; $dx <= 2; ++$dx){
			for($dz = -2; $dz <= 2; ++$dz){
				$this->set($volume, $dx, 0, $dz, $this->netherBricksStateId);
				for($dy = 1; $dy <= 3; ++$dy){
					$this->set($volume, $dx, $dy, $dz, $this->airStateId);
				}
			}
		}
		foreach([[-2, -2], [-2, 2], [2, -2], [2, 2]] as [$cx, $cz]){
			for($dy = 1; $dy <= self::SUPPORT_DEPTH; ++$dy){
				$this->set($volume, $cx, -$dy, $cz, $this->netherBricksStateId);
			}
		}
		//rim railings, leaving the four mid-edge cells open where the bridges attach
		for($i = -2; $i <= 2; ++$i){
			if($i === 0){
				continue;
			}
			$this->set($volume, $i, 1, -2, $this->fenceStateId);
			$this->set($volume, $i, 1, 2, $this->fenceStateId);
			$this->set($volume, -2, 1, $i, $this->fenceStateId);
			$this->set($volume, 2, 1, $i, $this->fenceStateId);
		}
	}

	private function buildBridge(GenerationVolume $volume, int $facing, int $length) : void{
		[$fdx, $fdz, $pdx, $pdz] = self::axes($facing);
		//start at 3 so the deck (which reaches forward offset 2) connects seamlessly to the bridge
		for($s = 3; $s <= $length; ++$s){
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
	}

	private function buildArch(GenerationVolume $volume, int $facing, int $s) : void{
		[$fdx, $fdz, $pdx, $pdz] = self::axes($facing);
		$facingFromNeg = $pdz !== 0 ? Facing::SOUTH : Facing::EAST; //inward from the perp -1 column
		$facingFromPos = $pdz !== 0 ? Facing::NORTH : Facing::WEST; //inward from the perp +1 column

		for($depth = 1; $depth <= self::SUPPORT_DEPTH; ++$depth){
			$this->set($volume, $fdx * $s + $pdx * -1, -$depth, $fdz * $s + $pdz * -1, $this->netherBricksStateId);
			$this->set($volume, $fdx * $s + $pdx * 1, -$depth, $fdz * $s + $pdz * 1, $this->netherBricksStateId);
		}
		//arch shoulders: upside-down stairs just under the deck edges, curving inward
		$this->setStair($volume, $fdx * $s + $pdx * -1, -1, $fdz * $s + $pdz * -1, $facingFromNeg, true);
		$this->setStair($volume, $fdx * $s + $pdx * 1, -1, $fdz * $s + $pdz * 1, $facingFromPos, true);
	}

	private function buildBalcony(GenerationVolume $volume, int $facing, int $armLen) : void{
		[$fdx, $fdz, $pdx, $pdz] = self::axes($facing);
		$cf = $armLen + 3; //forward offset of the balcony centre

		for($a = -2; $a <= 2; ++$a){
			for($b = -2; $b <= 2; ++$b){
				$dx = $fdx * ($cf + $a) + $pdx * $b;
				$dz = $fdz * ($cf + $a) + $pdz * $b;
				$this->set($volume, $dx, 0, $dz, $this->netherBricksStateId);
				for($dy = 1; $dy <= 3; ++$dy){
					$this->set($volume, $dx, $dy, $dz, $this->airStateId);
				}
				$onRim = $a === -2 || $a === 2 || $b === -2 || $b === 2;
				$bridgeOpening = $a === -2 && $b === 0;
				if($onRim && !$bridgeOpening){
					$this->set($volume, $dx, 1, $dz, $this->fenceStateId);
				}
			}
		}
		foreach([[-2, -2], [-2, 2], [2, -2], [2, 2]] as [$a, $b]){
			$dx = $fdx * ($cf + $a) + $pdx * $b;
			$dz = $fdz * ($cf + $a) + $pdz * $b;
			for($dy = 1; $dy <= self::SUPPORT_DEPTH; ++$dy){
				$this->set($volume, $dx, -$dy, $dz, $this->netherBricksStateId);
			}
		}
		//the blaze spawner sits on the open balcony floor at the centre
		$this->set($volume, $fdx * $cf, 1, $fdz * $cf, $this->spawnerStateId);
	}

	private function buildWartRoom(GenerationVolume $volume, int $facing, int $branchLen) : void{
		[$fdx, $fdz, $pdx, $pdz] = self::axes($facing);
		$cf = $branchLen + 3; //forward offset of the room centre

		//enclosed 5x5 room: nether brick floor, walls and ceiling, hollow interior
		for($a = -2; $a <= 2; ++$a){
			for($b = -2; $b <= 2; ++$b){
				$dx = $fdx * ($cf + $a) + $pdx * $b;
				$dz = $fdz * ($cf + $a) + $pdz * $b;
				$onWall = $a === -2 || $a === 2 || $b === -2 || $b === 2;
				$this->set($volume, $dx, 0, $dz, $this->netherBricksStateId);
				$this->set($volume, $dx, 4, $dz, $this->netherBricksStateId);
				for($dy = 1; $dy <= 3; ++$dy){
					$this->set($volume, $dx, $dy, $dz, $onWall ? $this->netherBricksStateId : $this->airStateId);
				}
			}
		}
		//doorway (2 tall) in the wall facing the bridge
		$this->set($volume, $fdx * ($cf - 2), 1, $fdz * ($cf - 2), $this->airStateId);
		$this->set($volume, $fdx * ($cf - 2), 2, $fdz * ($cf - 2), $this->airStateId);

		//soul sand bed with nether wart on the inner 3x3 floor
		for($a = -1; $a <= 1; ++$a){
			for($b = -1; $b <= 1; ++$b){
				$dx = $fdx * ($cf + $a) + $pdx * $b;
				$dz = $fdz * ($cf + $a) + $pdz * $b;
				$this->set($volume, $dx, 0, $dz, $this->soulSandStateId);
				$this->set($volume, $dx, 1, $dz, $this->netherWartStateId);
			}
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
