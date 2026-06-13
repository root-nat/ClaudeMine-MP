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

namespace pocketmine\world\generator\carver;

use pocketmine\utils\Random;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\noise\Simplex;
use function abs;
use function ceil;
use function cos;
use function max;
use function min;
use function sin;
use const M_PI;

/**
 * Carves 1.18-style caves: a "cheese" pass (large rooms where 3D Simplex noise crosses zero) and a "spaghetti" pass
 * (thin worm tunnels walked from region-seeded origins). Pure logic over a {@link GenerationVolume}; carvable blocks
 * and the air state ID are injected so the carver stays decoupled from the block registry and unit-testable.
 */
final class CaveCarver implements Carver{

	private const NOISE_SCALE = 0.0125;
	private const CHEESE_THRESHOLD = 0.082;
	private const WORMS_PER_REGION_MAX = 2;
	private const WORM_STEP_LENGTH = 0.85;

	/** @phpstan-var \Closure(int) : bool */
	private \Closure $canReplace;

	private int $cachedLowerY = 0;
	private int $cachedUpperY = 0;

	/**
	 * @phpstan-param \Closure(int) : bool $canReplace
	 */
	public function __construct(
		private int $airStateId,
		\Closure $canReplace,
		private int $floorMargin = 1,
		private int $maxCarveY = 50,
		private int $minCarveY = -59
	){
		$this->canReplace = $canReplace;
	}

	public function carve(GenerationVolume $volume, int $chunkX, int $chunkZ, int $worldSeed) : void{
		$this->cachedLowerY = $this->lowerY($volume);
		$this->cachedUpperY = $this->upperY($volume);
		$this->cheesePass($volume, $chunkX, $chunkZ, $worldSeed);
		$this->spaghettiPass($volume, $chunkX, $chunkZ, $worldSeed);
	}

	private function lowerY(GenerationVolume $volume) : int{
		return max($volume->getMinY() + $this->floorMargin, $this->minCarveY);
	}

	private function upperY(GenerationVolume $volume) : int{
		return min($volume->getMaxY() - 1, $this->maxCarveY);
	}

	private function cheesePass(GenerationVolume $volume, int $chunkX, int $chunkZ, int $worldSeed) : void{
		$noise = new Simplex(new Random($worldSeed & 0x7fffffff), 4, 0.5, 1.0);
		$baseX = $chunkX * Chunk::EDGE_LENGTH;
		$baseZ = $chunkZ * Chunk::EDGE_LENGTH;
		$lower = $this->lowerY($volume);
		$upper = $this->upperY($volume);

		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			$wx = $baseX + $x;
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				$wz = $baseZ + $z;
				for($y = $lower; $y <= $upper; ++$y){
					$n = $noise->noise3D($wx * self::NOISE_SCALE, $y * self::NOISE_SCALE, $wz * self::NOISE_SCALE, true);
					if(abs($n) < self::CHEESE_THRESHOLD){
						$this->carveCell($volume, $wx, $y, $wz);
					}
				}
			}
		}
	}

	private function spaghettiPass(GenerationVolume $volume, int $chunkX, int $chunkZ, int $worldSeed) : void{
		//worms originating in this chunk and its 8 neighbours may reach into this chunk
		for($rx = $chunkX - 1; $rx <= $chunkX + 1; ++$rx){
			for($rz = $chunkZ - 1; $rz <= $chunkZ + 1; ++$rz){
				$random = RegionRandom::derive($worldSeed, $rx, $rz, 0x2bd4);
				$wormCount = $random->nextBoundedInt(self::WORMS_PER_REGION_MAX + 1);
				for($w = 0; $w < $wormCount; ++$w){
					$this->walkWorm($volume, $rx, $rz, $random);
				}
			}
		}
	}

	private function walkWorm(GenerationVolume $volume, int $regionX, int $regionZ, Random $random) : void{
		$x = $regionX * Chunk::EDGE_LENGTH + $random->nextBoundedInt(Chunk::EDGE_LENGTH) + 0.5;
		$z = $regionZ * Chunk::EDGE_LENGTH + $random->nextBoundedInt(Chunk::EDGE_LENGTH) + 0.5;
		$lower = $this->lowerY($volume);
		$upper = $this->upperY($volume);
		$y = (float) ($lower + $random->nextBoundedInt(max(1, $upper - $lower)));

		$yaw = $random->nextFloat() * M_PI * 2;
		$pitch = ($random->nextFloat() - 0.5) * 0.6;
		$radius = 1.4 + $random->nextFloat() * 1.4;
		$steps = 24 + $random->nextBoundedInt(40);

		for($i = 0; $i < $steps; ++$i){
			$this->carveSphere($volume, $x, $y, $z, $radius);

			$yaw += ($random->nextFloat() - 0.5) * 0.5;
			$pitch = $pitch * 0.85 + ($random->nextFloat() - 0.5) * 0.2;

			$x += cos($yaw) * self::WORM_STEP_LENGTH;
			$z += sin($yaw) * self::WORM_STEP_LENGTH;
			$y += sin($pitch) * self::WORM_STEP_LENGTH;
		}
	}

	private function carveSphere(GenerationVolume $volume, float $cx, float $cy, float $cz, float $radius) : void{
		$r2 = $radius * $radius;
		$ri = (int) ceil($radius);
		$baseX = (int) $cx;
		$baseY = (int) $cy;
		$baseZ = (int) $cz;

		for($dx = -$ri; $dx <= $ri; ++$dx){
			for($dy = -$ri; $dy <= $ri; ++$dy){
				for($dz = -$ri; $dz <= $ri; ++$dz){
					if(($dx * $dx) + ($dy * $dy) + ($dz * $dz) <= $r2){
						$this->carveCell($volume, $baseX + $dx, $baseY + $dy, $baseZ + $dz);
					}
				}
			}
		}
	}

	private function carveCell(GenerationVolume $volume, int $x, int $y, int $z) : void{
		if($y < $this->cachedLowerY || $y > $this->cachedUpperY){
			return;
		}
		if(!$volume->isInBounds($x, $y, $z)){
			return;
		}
		if(($this->canReplace)($volume->getBlockStateId($x, $y, $z))){
			$volume->setBlockStateId($x, $y, $z, $this->airStateId);
		}
	}
}
