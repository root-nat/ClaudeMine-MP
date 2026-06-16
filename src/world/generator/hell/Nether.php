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

namespace pocketmine\world\generator\hell;

use pocketmine\block\Block;
use pocketmine\block\NetherWartPlant;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\math\Facing;
use pocketmine\world\biome\BiomeRegistry;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\InvalidGeneratorOptionsException;
use pocketmine\world\generator\noise\Simplex;
use pocketmine\world\generator\object\OreType;
use pocketmine\world\generator\populator\NetherDecoration;
use pocketmine\world\generator\populator\Ore;
use pocketmine\world\generator\populator\Populator;
use pocketmine\world\generator\structure\NetherFortressPopulator;
use pocketmine\world\generator\structure\NetherFortressStructure;
use pocketmine\world\World;
use function abs;

class Nether extends Generator{

	private int $waterHeight = 32;
	private int $emptyHeight = 64;
	private int $emptyAmplitude = 1;
	private float $density = 0.5;

	/** @var Populator[] */
	private array $populators = [];
	/** @var Populator[] */
	private array $generationPopulators = [];
	private Simplex $noiseBase;
	/** Low-frequency field that carves the Nether into biome regions (crimson/warped forests, soul valley, basalt deltas). */
	private Simplex $biomeNoise;

	/**
	 * @throws InvalidGeneratorOptionsException
	 */
	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);

		$this->noiseBase = new Simplex($this->random, 4, 1 / 4, 1 / 64);
		$this->biomeNoise = new Simplex($this->random, 3, 1 / 2, 1 / 256);
		$this->random->setSeed($this->seed);

		$ores = new Ore();
		$ores->setOreTypes([
			new OreType(VanillaBlocks::NETHER_QUARTZ_ORE(), VanillaBlocks::NETHERRACK(), 16, 14, 10, 117),
			new OreType(VanillaBlocks::NETHER_GOLD_ORE(), VanillaBlocks::NETHERRACK(), 10, 10, 10, 117),
			//ancient debris (netherite): a rare deep blob near Y15, plus an even rarer high band - vanilla-ish distribution
			new OreType(VanillaBlocks::ANCIENT_DEBRIS(), VanillaBlocks::NETHERRACK(), 1, 3, 8, 22),
			new OreType(VanillaBlocks::ANCIENT_DEBRIS(), VanillaBlocks::NETHERRACK(), 1, 2, 8, 119)
		]);
		$this->populators[] = $ores;

		$this->populators[] = new NetherDecoration();

		//nether brick fortresses: arched bridges with a blaze-spawner balcony and a soul-sand nether-wart room, spanning
		//the lava seas at a fixed deck height
		$stairStateIds = [];
		$stairUpsideDownStateIds = [];
		foreach([Facing::NORTH, Facing::SOUTH, Facing::EAST, Facing::WEST] as $facing){
			$stairStateIds[$facing] = VanillaBlocks::NETHER_BRICK_STAIRS()->setFacing($facing)->getStateId();
			$stairUpsideDownStateIds[$facing] = VanillaBlocks::NETHER_BRICK_STAIRS()->setFacing($facing)->setUpsideDown(true)->getStateId();
		}
		$fortress = new NetherFortressStructure(
			Block::EMPTY_STATE_ID,
			VanillaBlocks::NETHER_BRICKS()->getStateId(),
			VanillaBlocks::NETHER_BRICK_FENCE()->getStateId(),
			VanillaBlocks::SOUL_SAND()->getStateId(),
			VanillaBlocks::NETHER_WART()->setAge(NetherWartPlant::MAX_AGE)->getStateId(),
			VanillaBlocks::MONSTER_SPAWNER()->getStateId(),
			$stairStateIds,
			$stairUpsideDownStateIds
		);
		$this->populators[] = new NetherFortressPopulator($this->seed, $fortress);
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->seed);

		$noise = $this->noiseBase->getFastNoise3D(Chunk::EDGE_LENGTH, 128, Chunk::EDGE_LENGTH, 4, 8, 4, $chunkX * Chunk::EDGE_LENGTH, 0, $chunkZ * Chunk::EDGE_LENGTH);

		//TODO: why don't we just create and set the chunk here directly?
		$chunk = $world->getChunk($chunkX, $chunkZ) ?? throw new \InvalidArgumentException("Chunk $chunkX $chunkZ does not yet exist");

		$bedrock = VanillaBlocks::BEDROCK()->getStateId();
		$netherrack = VanillaBlocks::NETHERRACK()->getStateId();
		$stillLava = VanillaBlocks::LAVA()->getStateId();
		$air = VanillaBlocks::AIR()->getStateId();

		//one biome per chunk, chosen from a low-frequency field, so the Nether breaks into large crimson/warped/soul/basalt regions
		$biomeId = $this->pickBiome($this->biomeNoise->noise2D($chunkX * Chunk::EDGE_LENGTH + 7, $chunkZ * Chunk::EDGE_LENGTH + 7, true));
		$surface = $this->surfaceFor($biomeId);

		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				//only the Nether's actual 0..127 band needs a biome id; writing the full -64..319 world height was pure overhead
				for($y = 0; $y < 128; ++$y){
					$chunk->setBiomeId($x, $y, $z, $biomeId);
				}

				for($y = 0; $y < 128; ++$y){
					if($y === 0 || $y === 127){
						$chunk->setBlockStateId($x, $y, $z, $bedrock);
						continue;
					}
					$noiseValue = (abs($this->emptyHeight - $y) / $this->emptyHeight) * $this->emptyAmplitude - $noise[$x][$z][$y];
					$noiseValue -= 1 - $this->density;

					if($noiseValue > 0){
						$chunk->setBlockStateId($x, $y, $z, $netherrack);
					}elseif($y <= $this->waterHeight){
						$chunk->setBlockStateId($x, $y, $z, $stillLava);
					}
				}

				//lay the biome's surface over every exposed netherrack floor (nylium / soul sand / basalt), a few blocks deep
				if($surface !== null){
					[$top, $sub, $subDepth] = $surface;
					$aboveIsAir = false;
					for($y = 126; $y >= 1; --$y){
						$id = $chunk->getBlockStateId($x, $y, $z);
						if($id === $netherrack && $aboveIsAir){
							$chunk->setBlockStateId($x, $y, $z, $top);
							for($d = 1; $d <= $subDepth; ++$d){
								if($chunk->getBlockStateId($x, $y - $d, $z) === $netherrack){
									$chunk->setBlockStateId($x, $y - $d, $z, $sub);
								}
							}
						}
						$aboveIsAir = ($id === $air);
					}
				}
			}
		}

		foreach($this->generationPopulators as $populator){
			$populator->populate($world, $chunkX, $chunkZ, $this->random);
		}
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->seed);
		foreach($this->populators as $populator){
			$populator->populate($world, $chunkX, $chunkZ, $this->random);
		}

		$chunk = $world->getChunk($chunkX, $chunkZ);
		$biome = BiomeRegistry::getInstance()->getBiome($chunk->getBiomeId(7, 7, 7));
		$biome->populateChunk($world, $chunkX, $chunkZ, $this->random);
	}

	private function pickBiome(float $noise) : int{
		if($noise < -0.5){
			return BiomeIds::SOULSAND_VALLEY;
		}
		if($noise < -0.2){
			return BiomeIds::WARPED_FOREST;
		}
		if($noise < 0.35){
			return BiomeIds::HELL; //nether wastes - the widest band, so the most common region
		}
		if($noise < 0.7){
			return BiomeIds::CRIMSON_FOREST;
		}
		return BiomeIds::BASALT_DELTAS;
	}

	/**
	 * The [top block, sub-surface block, sub-surface depth] state ids a biome lays over exposed netherrack, or null for
	 * the plain nether wastes (which keeps its netherrack).
	 *
	 * @return array{int, int, int}|null
	 */
	private function surfaceFor(int $biomeId) : ?array{
		return match($biomeId){
			BiomeIds::CRIMSON_FOREST => [VanillaBlocks::CRIMSON_NYLIUM()->getStateId(), VanillaBlocks::NETHERRACK()->getStateId(), 0],
			BiomeIds::WARPED_FOREST => [VanillaBlocks::WARPED_NYLIUM()->getStateId(), VanillaBlocks::NETHERRACK()->getStateId(), 0],
			BiomeIds::SOULSAND_VALLEY => [VanillaBlocks::SOUL_SAND()->getStateId(), VanillaBlocks::SOUL_SOIL()->getStateId(), 3],
			BiomeIds::BASALT_DELTAS => [VanillaBlocks::BASALT()->getStateId(), VanillaBlocks::BLACKSTONE()->getStateId(), 2],
			default => null
		};
	}
}
