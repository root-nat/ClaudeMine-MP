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

namespace pocketmine\world\generator\end;

use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\InvalidGeneratorOptionsException;
use pocketmine\world\generator\noise\Simplex;
use pocketmine\world\World;
use function cos;
use function intdiv;
use function max;
use function min;
use function sin;
use function sqrt;
use const M_PI;

class End extends Generator{

	public const ISLAND_RADIUS = 170;
	public const SURFACE_Y = 58;

	public const SPAWN_PLATFORM_X = 100;
	public const SPAWN_PLATFORM_Y = 48;
	public const SPAWN_PLATFORM_Z = 0;
	public const SPAWN_PLATFORM_RADIUS = 2;

	public const PILLAR_COUNT = 10;
	public const PILLAR_CIRCLE_RADIUS = 43;

	private Simplex $noiseBase;

	/**
	 * @var int[][] [x, z, radius, top y]
	 * @phpstan-var list<array{int, int, int, int}>
	 */
	private array $pillars = [];

	/**
	 * @throws InvalidGeneratorOptionsException
	 */
	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);

		$this->noiseBase = new Simplex($this->random, 4, 1 / 4, 1 / 32);
		$this->random->setSeed($this->seed);

		for($i = 0; $i < self::PILLAR_COUNT; ++$i){
			$angle = 2 * M_PI * $i / self::PILLAR_COUNT;
			$this->pillars[] = [
				(int) (self::PILLAR_CIRCLE_RADIUS * cos($angle)),
				(int) (self::PILLAR_CIRCLE_RADIUS * sin($angle)),
				2 + intdiv($i, 3),
				76 + $i * 3
			];
		}
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->seed);

		$noise = $this->noiseBase->getFastNoise2D(Chunk::EDGE_LENGTH, Chunk::EDGE_LENGTH, 4, $chunkX * Chunk::EDGE_LENGTH, 0, $chunkZ * Chunk::EDGE_LENGTH);

		$chunk = $world->getChunk($chunkX, $chunkZ) ?? throw new \InvalidArgumentException("Chunk $chunkX $chunkZ does not yet exist");

		$endStone = VanillaBlocks::END_STONE()->getStateId();
		$obsidian = VanillaBlocks::OBSIDIAN()->getStateId();

		$baseX = $chunkX * Chunk::EDGE_LENGTH;
		$baseZ = $chunkZ * Chunk::EDGE_LENGTH;

		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			$worldX = $baseX + $x;
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				$worldZ = $baseZ + $z;

				for($y = World::Y_MIN; $y < World::Y_MAX; $y++){
					$chunk->setBiomeId($x, $y, $z, BiomeIds::THE_END);
				}

				$distance = sqrt($worldX * $worldX + $worldZ * $worldZ);
				if($distance < self::ISLAND_RADIUS){
					$edgeFalloff = 1.0 - ($distance / self::ISLAND_RADIUS) ** 2;
					$noiseRow = $noise[$x] ?? null;
					$noiseValue = $noiseRow !== null ? ($noiseRow[$z] ?? 0.0) : 0.0;

					$top = (int) (self::SURFACE_Y + $edgeFalloff * 6 + $noiseValue * 4);
					$bottom = (int) (self::SURFACE_Y - $edgeFalloff * 30 + $noiseValue * 3);

					for($y = max(0, $bottom); $y <= min(World::Y_MAX - 1, $top); ++$y){
						$chunk->setBlockStateId($x, $y, $z, $endStone);
					}
				}

				foreach($this->pillars as [$pillarX, $pillarZ, $pillarRadius, $pillarTop]){
					$deltaX = $worldX - $pillarX;
					$deltaZ = $worldZ - $pillarZ;
					if($deltaX * $deltaX + $deltaZ * $deltaZ <= $pillarRadius * $pillarRadius){
						for($y = 40; $y <= $pillarTop; ++$y){
							$chunk->setBlockStateId($x, $y, $z, $obsidian);
						}
					}
				}

				if(
					$worldX >= self::SPAWN_PLATFORM_X - self::SPAWN_PLATFORM_RADIUS && $worldX <= self::SPAWN_PLATFORM_X + self::SPAWN_PLATFORM_RADIUS &&
					$worldZ >= self::SPAWN_PLATFORM_Z - self::SPAWN_PLATFORM_RADIUS && $worldZ <= self::SPAWN_PLATFORM_Z + self::SPAWN_PLATFORM_RADIUS
				){
					$chunk->setBlockStateId($x, self::SPAWN_PLATFORM_Y, $z, $obsidian);
					for($y = self::SPAWN_PLATFORM_Y + 1; $y <= self::SPAWN_PLATFORM_Y + 3; ++$y){
						$chunk->setBlockStateId($x, $y, $z, VanillaBlocks::AIR()->getStateId());
					}
				}
			}
		}
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{

	}
}
