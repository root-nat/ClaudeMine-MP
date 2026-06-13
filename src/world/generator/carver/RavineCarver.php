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
use function abs;
use function ceil;
use function cos;
use function max;
use function min;
use function sin;
use const M_PI;

/**
 * Carves rare ravines: long, tall, narrow vertical slits. Region-seeded so the same ravine is reproduced by every chunk
 * it passes through. Shares the carvable-block predicate / air-state injection of {@link CaveCarver}, keeping it pure
 * and unit-testable against a {@link GenerationVolume}.
 */
final class RavineCarver implements Carver{
	private const STEP_LENGTH = 0.9;

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
		private int $rarity = 60,
		private int $floorMargin = 1,
		private int $maxCarveY = 50,
		private int $minCarveY = -50
	){
		$this->canReplace = $canReplace;
	}

	public function carve(GenerationVolume $volume, int $chunkX, int $chunkZ, int $worldSeed) : void{
		$this->cachedLowerY = $this->lowerY($volume);
		$this->cachedUpperY = $this->upperY($volume);
		for($rx = $chunkX - 1; $rx <= $chunkX + 1; ++$rx){
			for($rz = $chunkZ - 1; $rz <= $chunkZ + 1; ++$rz){
				$random = RegionRandom::derive($worldSeed, $rx, $rz, 0x4f17);
				if($this->rarity > 1 && $random->nextBoundedInt($this->rarity) !== 0){
					continue;
				}
				$this->walkRavine($volume, $rx, $rz, $random);
			}
		}
	}

	private function lowerY(GenerationVolume $volume) : int{
		return max($volume->getMinY() + $this->floorMargin, $this->minCarveY);
	}

	private function upperY(GenerationVolume $volume) : int{
		return min($volume->getMaxY() - 1, $this->maxCarveY);
	}

	private function walkRavine(GenerationVolume $volume, int $regionX, int $regionZ, Random $random) : void{
		$x = $regionX * Chunk::EDGE_LENGTH + $random->nextBoundedInt(Chunk::EDGE_LENGTH) + 0.5;
		$z = $regionZ * Chunk::EDGE_LENGTH + $random->nextBoundedInt(Chunk::EDGE_LENGTH) + 0.5;

		$lower = $this->lowerY($volume);
		$upper = $this->upperY($volume);
		$span = max(1, $upper - $lower);
		$centerY = (float) ($lower + (int) ($span * 0.5) + $random->nextBoundedInt(max(1, (int) ($span * 0.25))));

		$halfHeight = 7.0 + $random->nextFloat() * 8.0;
		$width = 1.5 + $random->nextFloat() * 1.5;
		$yaw = $random->nextFloat() * M_PI * 2;
		$steps = 60 + $random->nextBoundedInt(50);

		for($i = 0; $i < $steps; ++$i){
			$taper = sin(($i / $steps) * M_PI); //0 at the ends, 1 in the middle
			$w = $width * (0.3 + 0.7 * $taper);
			$h = $halfHeight * (0.5 + 0.5 * $taper);
			$this->carveSlit($volume, $x, $centerY, $z, $w, $h);

			$yaw += ($random->nextFloat() - 0.5) * 0.18;
			$x += cos($yaw) * self::STEP_LENGTH;
			$z += sin($yaw) * self::STEP_LENGTH;
		}
	}

	private function carveSlit(GenerationVolume $volume, float $cx, float $cy, float $cz, float $radiusXZ, float $halfHeight) : void{
		$ri = (int) ceil($radiusXZ);
		$rh = (int) ceil($halfHeight);
		$baseX = (int) $cx;
		$baseY = (int) $cy;
		$baseZ = (int) $cz;
		$rxz2 = $radiusXZ * $radiusXZ;

		for($dy = -$rh; $dy <= $rh; ++$dy){
			//narrow the horizontal cross-section toward the top and bottom of the slit
			$yFactor = 1.0 - (abs($dy) / ($halfHeight + 0.001));
			if($yFactor <= 0){
				continue;
			}
			$rowR2 = $rxz2 * $yFactor;
			for($dx = -$ri; $dx <= $ri; ++$dx){
				for($dz = -$ri; $dz <= $ri; ++$dz){
					if(($dx * $dx) + ($dz * $dz) <= $rowR2){
						$this->carveCell($volume, $baseX + $dx, $baseY + $dy, $baseZ + $dz);
					}
				}
			}
		}
	}

	private function carveCell(GenerationVolume $volume, int $x, int $y, int $z) : void{
		if($y < $this->cachedLowerY || $y > $this->cachedUpperY || !$volume->isInBounds($x, $y, $z)){
			return;
		}
		if(($this->canReplace)($volume->getBlockStateId($x, $y, $z))){
			$volume->setBlockStateId($x, $y, $z, $this->airStateId);
		}
	}
}
