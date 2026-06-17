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

namespace pocketmine\world\generator\normal;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Liquid;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\utils\SlabType;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\biome\Biome;
use pocketmine\world\biome\BiomeRegistry;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use pocketmine\world\generator\carver\CarverPopulator;
use pocketmine\world\generator\carver\CaveCarver;
use pocketmine\world\generator\carver\RavineCarver;
use pocketmine\world\generator\Gaussian;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\InvalidGeneratorOptionsException;
use pocketmine\world\generator\noise\Simplex;
use pocketmine\world\generator\object\OreType;
use pocketmine\world\generator\populator\GroundCover;
use pocketmine\world\generator\populator\Ore;
use pocketmine\world\generator\populator\Populator;
use pocketmine\world\generator\structure\DesertTempleStructure;
use pocketmine\world\generator\structure\DesertWellStructure;
use pocketmine\world\generator\structure\AmethystGeodeStructure;
use pocketmine\world\generator\structure\BuriedTreasureStructure;
use pocketmine\world\generator\structure\DungeonStructure;
use pocketmine\world\generator\structure\FossilStructure;
use pocketmine\world\generator\structure\IglooStructure;
use pocketmine\world\generator\structure\JungleTempleStructure;
use pocketmine\world\generator\structure\OceanRuinsStructure;
use pocketmine\world\generator\structure\PillagerOutpostStructure;
use pocketmine\world\generator\structure\RuinedPortalStructure;
use pocketmine\world\generator\structure\ShipwreckStructure;
use pocketmine\world\generator\structure\StructurePopulator;
use pocketmine\world\generator\structure\SurfaceStructurePopulator;
use pocketmine\world\generator\structure\VillagePalette;
use pocketmine\world\generator\structure\VillageStructure;
use pocketmine\world\generator\structure\WitchHutStructure;
use pocketmine\world\World;
use function ceil;
use function floor;
use function is_int;
use function max;
use function min;

class Normal extends Generator{

	private int $waterHeight = 62;
	/** @var Populator[] */
	private array $populators = [];
	/** @var Populator[] */
	private array $generationPopulators = [];
	private Simplex $noiseBase;
	private OverworldBiomeSelector $selector;
	private Gaussian $gaussian;

	private const NOISE_SAMPLING_RATE_Y = 8;

	/**
	 * @throws InvalidGeneratorOptionsException
	 */
	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);

		$this->gaussian = new Gaussian(2);

		$this->noiseBase = new Simplex($this->random, 4, 1 / 4, 1 / 32);
		$this->random->setSeed($this->seed);

		$this->selector = new OverworldBiomeSelector($this->random);
		$this->selector->recalculate();

		//caves and ravines must be carved BEFORE GroundCover so the surface layer is re-applied over exposed cave faces
		$registry = RuntimeBlockStateRegistry::getInstance();
		$canCarve = static function(int $stateId) use ($registry) : bool{
			$block = $registry->fromStateId($stateId);
			if($block instanceof Liquid){
				return false;
			}
			$typeId = $block->getTypeId();
			return $typeId !== BlockTypeIds::AIR && $typeId !== BlockTypeIds::BEDROCK;
		};
		$air = Block::EMPTY_STATE_ID;
		$this->generationPopulators[] = new CarverPopulator($this->seed, [
			new CaveCarver($air, $canCarve),
			new RavineCarver($air, $canCarve)
		]);

		$cover = new GroundCover();
		$this->generationPopulators[] = $cover;

		$ores = new Ore();
		$stone = VanillaBlocks::STONE();
		$ores->setOreTypes([
			new OreType(VanillaBlocks::COAL_ORE(), $stone, 20, 16, 0, 128),
			new OreType(VanillaBlocks::IRON_ORE(), $stone, 20, 8, 0, 64),
			new OreType(VanillaBlocks::REDSTONE_ORE(), $stone, 8, 7, 0, 16),
			new OreType(VanillaBlocks::LAPIS_LAZULI_ORE(), $stone, 1, 6, 0, 32),
			new OreType(VanillaBlocks::GOLD_ORE(), $stone, 2, 8, 0, 32),
			new OreType(VanillaBlocks::DIAMOND_ORE(), $stone, 1, 7, 0, 16),
			new OreType(VanillaBlocks::DIRT(), $stone, 20, 32, 0, 128),
			new OreType(VanillaBlocks::GRAVEL(), $stone, 10, 16, 0, 128)
		]);
		$this->populators[] = $ores;

		//dungeons spawn in caves after terrain + carving are complete
		$dungeon = new DungeonStructure(
			Block::EMPTY_STATE_ID,
			VanillaBlocks::COBBLESTONE()->getStateId(),
			VanillaBlocks::MOSSY_COBBLESTONE()->getStateId(),
			VanillaBlocks::MONSTER_SPAWNER()->getStateId(),
			VanillaBlocks::CHEST()->getStateId()
		);
		$this->populators[] = new StructurePopulator($this->seed, $dungeon, rarity: StructurePopulator::DEFAULT_RARITY, airStateId: Block::EMPTY_STATE_ID);

		//surface structures: anchored on the top solid ground block, gated by biome, built around an untouched anchor
		//column so each chunk re-derives them identically. Loot chests are filled main-thread by OverworldStructureFurnisher
		//(registered for 'normal'/'default' in ChunkFurnisherRegistry).
		$desertWell = new DesertWellStructure(
			VanillaBlocks::SANDSTONE()->getStateId(),
			VanillaBlocks::SANDSTONE_SLAB()->setSlabType(SlabType::TOP)->getStateId(),
			VanillaBlocks::WATER()->getStateId()
		);
		$this->populators[] = new SurfaceStructurePopulator($this->seed, $desertWell, salt: DesertWellStructure::SALT, rarity: DesertWellStructure::RARITY, biomeAllow: [BiomeIds::DESERT], maxRadius: DesertWellStructure::MAX_RADIUS, surfaceTopY: 120, surfaceMinY: 40);

		$desertTemple = new DesertTempleStructure(
			Block::EMPTY_STATE_ID,
			VanillaBlocks::SANDSTONE()->getStateId(),
			VanillaBlocks::CUT_SANDSTONE()->getStateId(),
			VanillaBlocks::CHISELED_SANDSTONE()->getStateId(),
			VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::ORANGE)->getStateId(),
			VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::BLUE)->getStateId(),
			VanillaBlocks::STONE_PRESSURE_PLATE()->getStateId(),
			VanillaBlocks::TNT()->getStateId(),
			VanillaBlocks::CHEST()->getStateId()
		);
		$this->populators[] = new SurfaceStructurePopulator(
			$this->seed,
			$desertTemple,
			salt: DesertTempleStructure::SALT,
			rarity: DesertTempleStructure::RARITY,
			biomeAllow: [BiomeIds::DESERT],
			maxRadius: DesertTempleStructure::MAX_RADIUS,
			surfaceTopY: DesertTempleStructure::SURFACE_TOP_Y,
			surfaceMinY: DesertTempleStructure::SURFACE_MIN_Y
		);

		//fossils: buried bone-and-coal skeletons in desert and swamp (no loot)
		$fossil = new FossilStructure(
			VanillaBlocks::BONE_BLOCK()->setAxis(Axis::Y)->getStateId(),
			VanillaBlocks::COAL_ORE()->getStateId()
		);
		$this->populators[] = new SurfaceStructurePopulator($this->seed, $fossil, salt: FossilStructure::SALT, rarity: FossilStructure::RARITY, biomeAllow: [BiomeIds::DESERT, BiomeIds::SWAMPLAND], maxRadius: FossilStructure::MAX_RADIUS, surfaceTopY: 120, surfaceMinY: 40);

		//swamp huts: stilted spruce shacks with a witch (spawned by OverworldStructureFurnisher)
		$witchHut = new WitchHutStructure(
			Block::EMPTY_STATE_ID,
			VanillaBlocks::SPRUCE_PLANKS()->getStateId(),
			VanillaBlocks::SPRUCE_LOG()->setAxis(Axis::Y)->getStateId(),
			VanillaBlocks::OAK_FENCE()->getStateId(),
			VanillaBlocks::CAULDRON()->getStateId(),
			VanillaBlocks::CRAFTING_TABLE()->getStateId()
		);
		$this->populators[] = new SurfaceStructurePopulator($this->seed, $witchHut, salt: WitchHutStructure::SALT, rarity: WitchHutStructure::RARITY, biomeAllow: [BiomeIds::SWAMPLAND], maxRadius: WitchHutStructure::MAX_RADIUS, surfaceTopY: 120, surfaceMinY: 40);

		//jungle temples: sunk mossy-cobblestone halls with two loot chests
		$jungleTemple = new JungleTempleStructure(
			Block::EMPTY_STATE_ID,
			VanillaBlocks::COBBLESTONE()->getStateId(),
			VanillaBlocks::MOSSY_COBBLESTONE()->getStateId(),
			VanillaBlocks::CHEST()->getStateId()
		);
		$this->populators[] = new SurfaceStructurePopulator($this->seed, $jungleTemple, salt: JungleTempleStructure::SALT, rarity: JungleTempleStructure::RARITY, biomeAllow: [BiomeIds::JUNGLE], maxRadius: JungleTempleStructure::MAX_RADIUS, surfaceTopY: 120, surfaceMinY: 40);

		//pillager outposts: dark-oak watchtowers with a loot chest and pillagers (spawned by the furnisher)
		$pillagerOutpost = new PillagerOutpostStructure(
			Block::EMPTY_STATE_ID,
			VanillaBlocks::DARK_OAK_LOG()->setAxis(Axis::Y)->getStateId(),
			VanillaBlocks::DARK_OAK_PLANKS()->getStateId(),
			VanillaBlocks::DARK_OAK_FENCE()->getStateId(),
			VanillaBlocks::COBBLESTONE()->getStateId(),
			VanillaBlocks::LADDER()->setFacing(Facing::SOUTH)->getStateId(),
			VanillaBlocks::CHEST()->getStateId()
		);
		$this->populators[] = new SurfaceStructurePopulator($this->seed, $pillagerOutpost, salt: PillagerOutpostStructure::SALT, rarity: PillagerOutpostStructure::RARITY, biomeAllow: [BiomeIds::PLAINS, BiomeIds::DESERT, BiomeIds::TAIGA], maxRadius: PillagerOutpostStructure::MAX_RADIUS, surfaceTopY: 120, surfaceMinY: 40);

		//igloos: snow huts hiding a stone-brick basement with a loot chest
		$igloo = new IglooStructure(
			Block::EMPTY_STATE_ID,
			VanillaBlocks::SNOW()->getStateId(),
			VanillaBlocks::ICE()->getStateId(),
			VanillaBlocks::STONE_BRICKS()->getStateId(),
			VanillaBlocks::MOSSY_STONE_BRICKS()->getStateId(),
			VanillaBlocks::CRACKED_STONE_BRICKS()->getStateId(),
			VanillaBlocks::BREWING_STAND()->getStateId(),
			VanillaBlocks::CHEST()->getStateId()
		);
		$this->populators[] = new SurfaceStructurePopulator($this->seed, $igloo, salt: IglooStructure::SALT, rarity: IglooStructure::RARITY, biomeAllow: [BiomeIds::ICE_PLAINS, BiomeIds::COLD_TAIGA], maxRadius: IglooStructure::MAX_RADIUS, surfaceTopY: 120, surfaceMinY: 40);

		//ruined portals: broken obsidian frames with a loot chest, in every land biome
		$ruinedPortal = new RuinedPortalStructure(
			VanillaBlocks::OBSIDIAN()->getStateId(),
			VanillaBlocks::CRYING_OBSIDIAN()->getStateId(),
			VanillaBlocks::NETHERRACK()->getStateId(),
			VanillaBlocks::GOLD()->getStateId(),
			VanillaBlocks::MAGMA()->getStateId(),
			VanillaBlocks::STONE_BRICKS()->getStateId(),
			VanillaBlocks::CHEST()->getStateId()
		);
		$this->populators[] = new SurfaceStructurePopulator($this->seed, $ruinedPortal, salt: RuinedPortalStructure::SALT, rarity: RuinedPortalStructure::RARITY, biomeAllow: [], maxRadius: RuinedPortalStructure::MAX_RADIUS, surfaceTopY: 120, surfaceMinY: 40);

		//amethyst geodes: buried concentric basalt/calcite/amethyst shells (any biome, no loot)
		$geode = new AmethystGeodeStructure(
			Block::EMPTY_STATE_ID,
			VanillaBlocks::SMOOTH_BASALT()->getStateId(),
			VanillaBlocks::CALCITE()->getStateId(),
			VanillaBlocks::AMETHYST()->getStateId(),
			VanillaBlocks::BUDDING_AMETHYST()->getStateId()
		);
		$this->populators[] = new SurfaceStructurePopulator($this->seed, $geode, salt: AmethystGeodeStructure::SALT, rarity: AmethystGeodeStructure::RARITY, biomeAllow: [], maxRadius: AmethystGeodeStructure::MAX_RADIUS, surfaceTopY: 120, surfaceMinY: 40);

		//shipwrecks: broken wooden hulls on the ocean floor with two loot chests (surface scan skips water -> seabed)
		$shipwreck = new ShipwreckStructure(
			VanillaBlocks::OAK_PLANKS()->getStateId(),
			VanillaBlocks::OAK_LOG()->setAxis(Axis::Y)->getStateId(),
			VanillaBlocks::OAK_FENCE()->getStateId(),
			VanillaBlocks::CHEST()->getStateId()
		);
		$oceans = [BiomeIds::OCEAN, BiomeIds::DEEP_OCEAN, BiomeIds::WARM_OCEAN, BiomeIds::LUKEWARM_OCEAN, BiomeIds::COLD_OCEAN, BiomeIds::FROZEN_OCEAN];
		$this->populators[] = new SurfaceStructurePopulator($this->seed, $shipwreck, salt: ShipwreckStructure::SALT, rarity: ShipwreckStructure::RARITY, biomeAllow: $oceans, maxRadius: ShipwreckStructure::MAX_RADIUS, surfaceTopY: 120, surfaceMinY: 30);

		//buried treasure: a single Heart-of-the-Sea chest sunk under the ocean floor or a beach
		$buriedTreasure = new BuriedTreasureStructure(VanillaBlocks::CHEST()->getStateId());
		$this->populators[] = new SurfaceStructurePopulator($this->seed, $buriedTreasure, salt: BuriedTreasureStructure::SALT, rarity: BuriedTreasureStructure::RARITY, biomeAllow: [...$oceans, BiomeIds::BEACH], maxRadius: BuriedTreasureStructure::MAX_RADIUS, surfaceTopY: 120, surfaceMinY: 30);

		//ocean ruins: weathered stone-brick buildings on the seabed with a loot chest
		$oceanRuins = new OceanRuinsStructure(
			VanillaBlocks::STONE_BRICKS()->getStateId(),
			VanillaBlocks::MOSSY_STONE_BRICKS()->getStateId(),
			VanillaBlocks::CRACKED_STONE_BRICKS()->getStateId(),
			VanillaBlocks::SAND()->getStateId(),
			VanillaBlocks::CHEST()->getStateId()
		);
		$this->populators[] = new SurfaceStructurePopulator($this->seed, $oceanRuins, salt: OceanRuinsStructure::SALT, rarity: OceanRuinsStructure::RARITY, biomeAllow: $oceans, maxRadius: OceanRuinsStructure::MAX_RADIUS, surfaceTopY: 120, surfaceMinY: 30);

		//villages: a multi-piece jigsaw structure (well plaza + paths + houses) assembled deterministically per region and
		//furnished on the main thread by VillageFurnisher. One populator per biome theme; all share VillageStructure::SALT
		//so they draw the SAME anchors, and the disjoint biome gates pick which palette (oak/sandstone/spruce) generates.
		foreach([
			[VillagePalette::plains(), [BiomeIds::PLAINS, BiomeIds::SAVANNA, BiomeIds::TAIGA]],
			[VillagePalette::desert(), [BiomeIds::DESERT]],
			[VillagePalette::snowy(), [BiomeIds::ICE_PLAINS, BiomeIds::COLD_TAIGA]]
		] as [$villagePalette, $villageBiomes]){
			$this->populators[] = new SurfaceStructurePopulator($this->seed, new VillageStructure($villagePalette), salt: VillageStructure::SALT, rarity: VillageStructure::RARITY, biomeAllow: $villageBiomes, maxRadius: VillageStructure::MAX_RADIUS, surfaceTopY: VillageStructure::SURFACE_TOP_Y, surfaceMinY: VillageStructure::SURFACE_MIN_Y);
		}
	}

	private function pickBiome(int $x, int $z) : Biome{
		//the coordinate jitter that keeps biome borders from being axis-aligned now lives on the selector, so the /locate
		//command can reproduce the exact same biome at any column from the world seed
		return $this->selector->pickBiomeJittered($x, $z, $this->seed);
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->seed);

		//TODO: why don't we just create and set the chunk here directly?
		$chunk = $world->getChunk($chunkX, $chunkZ) ?? throw new \InvalidArgumentException("Chunk $chunkX $chunkZ does not yet exist");

		$bedrock = VanillaBlocks::BEDROCK()->getStateId();
		$stillWater = VanillaBlocks::WATER()->getStateId();
		$stone = VanillaBlocks::STONE()->getStateId();

		$baseX = $chunkX * Chunk::EDGE_LENGTH;
		$baseZ = $chunkZ * Chunk::EDGE_LENGTH;

		[$biomeArray, $minNoiseHeights, $maxNoiseHeights] = $this->generateBiomes($baseX, $baseZ);

		$lowestNoiseBlock = (int) floor(min($minNoiseHeights));
		$highestNoiseBlock = (int) ceil(max($maxNoiseHeights));

		//getFastNoise3D expects the inputs to be aligned with the sampling rate, otherwise the samples will be taken
		//from different coordinates than we originally used when we first implemented this generator
		$noiseMin = (int) floor($lowestNoiseBlock / self::NOISE_SAMPLING_RATE_Y) * self::NOISE_SAMPLING_RATE_Y;
		$noiseMax = (int) ceil($highestNoiseBlock / self::NOISE_SAMPLING_RATE_Y) * self::NOISE_SAMPLING_RATE_Y;

		//we only need to generate noise for the blocks which could be affected
		//outside these bounds we'll just flood-fill blocks to save time
		$noise = $this->noiseBase->getFastNoise3D(
			xSize: Chunk::EDGE_LENGTH,
			ySize: $noiseMax - $noiseMin,
			zSize: Chunk::EDGE_LENGTH,
			xSamplingRate: 4,
			ySamplingRate: self::NOISE_SAMPLING_RATE_Y,
			zSamplingRate: 4,
			x: $chunkX * Chunk::EDGE_LENGTH,
			y: $noiseMin,
			z: $chunkZ * Chunk::EDGE_LENGTH
		);

		$minNoiseSubChunk = (int) floor($noiseMin / SubChunk::EDGE_LENGTH);
		foreach($chunk->getSubChunks() as $y => $subChunk){
			if($y >= 0 && $y < $minNoiseSubChunk){
				//Everything above 0 and below noiseMin is always solid stone, which can be flood-filled instead of
				//setting the blocks one at a time - this is vastly faster
				$blocks = [new PalettedBlockArray($stone)];
			}else{
				$blocks = [];
			}
			$chunk->setSubChunk($y, new SubChunk(Block::EMPTY_STATE_ID, $blocks, clone $biomeArray));
		}

		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				$chunk->setBlockStateId($x, 0, $z, $bedrock);

				$columnIndex = World::chunkHash($x, $z);
				$minSum = $minNoiseHeights[$columnIndex];
				$maxSum = $maxNoiseHeights[$columnIndex];
				$maxBlockY = max($maxSum, $this->waterHeight);
				$smoothHeight = ($maxSum - $minSum) / 2;

				//Everything below minSum is always solid stone - we already flood-filled the subchunks below though, so
				//we only need to fill the gap in the column here
				for($y = $minNoiseSubChunk * SubChunk::EDGE_LENGTH; $y < $minSum; $y++){
					$chunk->setBlockStateId($x, $y, $z, $stone);
				}
				for($y = (int) floor($minSum); $y <= $maxBlockY; ++$y){
					//noiseValue would anyway be <= 0 above maxSum because the smoothing term is >= 1
					$noiseValue = $y > $noiseMax ?
						-1 :
						$noise[$x][$z][$y - $noiseMin] - 1 / $smoothHeight * ($y - $smoothHeight - $minSum);

					if($noiseValue > 0){
						$chunk->setBlockStateId($x, $y, $z, $stone);
					}elseif($y <= $this->waterHeight){
						$chunk->setBlockStateId($x, $y, $z, $stillWater);
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

	/**
	 * @return int[][]|PalettedBlockArray[]
	 * @phpstan-return array{PalettedBlockArray, non-empty-array<int, float>, non-empty-array<int, float>}
	 */
	private function generateBiomes(int $baseX, int $baseZ) : array{
		$biomeCache = [];

		$biomeArray = new PalettedBlockArray(BiomeIds::OCEAN);

		$uniform = null;
		$padding = $this->gaussian->smoothSize;
		$start = -$padding;
		$end = Chunk::EDGE_LENGTH + $padding;
		for($x = $start; $x < $end; ++$x){
			$absoluteX = $baseX + $x;
			for($z = $start; $z < $end; ++$z){
				$absoluteZ = $baseZ + $z;

				$columnIndex = World::chunkHash($x, $z);
				$biome = $biomeCache[$columnIndex] = $this->pickBiome($absoluteX, $absoluteZ);
				$biomeId = $biome->getId();
				$uniform = match ($uniform) {
					null, $biomeId => $biomeId,
					default => false
				};

				if($x >= 0 && $x < Chunk::EDGE_LENGTH && $z >= 0 && $z < Chunk::EDGE_LENGTH){
					for($y = 0; $y < 16; $y++){
						$biomeArray->set($x, $y, $z, $biomeId);
					}
				}
			}
		}

		if($uniform === false){
			[$minHeights, $maxHeights] = $this->gaussianSmoothElevation($start, $end, $biomeCache);
		}else{
			//If all the biomes in the blurred area are the same, we can save some performance by skipping blurring
			//With the current generator as of 2025-10-23, blurring can be skipped in two-thirds of chunks
			if(!is_int($uniform)){
				throw new AssumptionFailedError();
			}
			$biome = BiomeRegistry::getInstance()->getBiome($uniform);
			/** @phpstan-var non-empty-array<int, float> $minHeights */
			$minHeights = [];
			/** @phpstan-var non-empty-array<int, float> $maxHeights */
			$maxHeights = [];

			$minElevation = $biome->getMinElevation() - 1;
			$maxElevation = $biome->getMaxElevation();
			for($x = 0; $x < Chunk::EDGE_LENGTH; $x++){
				for($z = 0; $z < Chunk::EDGE_LENGTH; $z++){
					$columnIndex = World::chunkHash($x, $z);
					$minHeights[$columnIndex] = $minElevation;
					$maxHeights[$columnIndex] = $maxElevation;
				}
			}
		}

		return [$biomeArray, $minHeights, $maxHeights];
	}

	/**
	 * @param Biome[] $biomeCache
	 * @phpstan-param array<int, Biome> $biomeCache
	 *
	 * @return float[][]
	 * @phpstan-return array{non-empty-array<int, float>, non-empty-array<int, float>}
	 */
	private function gaussianSmoothElevation(int $start, int $end, array $biomeCache) : array{
		$minHeightsX = [];
		$maxHeightsX = [];
		//blur along the X axis first
		for($x = 0; $x < Chunk::EDGE_LENGTH; $x++){
			//while we don't need to smooth the padding corners, we do need to make sure that the contributions of
			//those corners are included in Z padding, otherwise we can get artifacts at chunk corners
			for($z = $start; $z < $end; $z++){
				$columnIndex = World::chunkHash($x, $z);

				$minSum = 0;
				$maxSum = 0;

				for($sx = -$this->gaussian->smoothSize; $sx <= $this->gaussian->smoothSize; ++$sx){
					$weight = $this->gaussian->kernel1D[$sx + $this->gaussian->smoothSize];
					$adjacent = $biomeCache[World::chunkHash($x + $sx, $z)];

					$minSum += ($adjacent->getMinElevation() - 1) * $weight;
					$maxSum += $adjacent->getMaxElevation() * $weight;
				}

				$minHeightsX[$columnIndex] = $minSum / $this->gaussian->weightSum1D;
				$maxHeightsX[$columnIndex] = $maxSum / $this->gaussian->weightSum1D;
			}
		}

		/** @phpstan-var non-empty-array<int, float> $minHeights */
		$minHeights = [];
		/** @phpstan-var non-empty-array<int, float> $maxHeights */
		$maxHeights = [];

		//then the Z axis, using the blurred values from the previous loop
		for($x = 0; $x < Chunk::EDGE_LENGTH; $x++){
			for($z = 0; $z < Chunk::EDGE_LENGTH; $z++){
				$columnIndex = World::chunkHash($x, $z);

				$minSum = 0;
				$maxSum = 0;

				for($sx = -$this->gaussian->smoothSize; $sx <= $this->gaussian->smoothSize; ++$sx){
					$weight = $this->gaussian->kernel1D[$sx + $this->gaussian->smoothSize];
					$adjacentIndex = World::chunkHash($x, $z + $sx);
					$minElevation = $minHeightsX[$adjacentIndex];
					$maxElevation = $maxHeightsX[$adjacentIndex];

					$minSum += $minElevation * $weight;
					$maxSum += $maxElevation * $weight;
				}

				$minHeights[$columnIndex] = $minSum / $this->gaussian->weightSum1D;
				$maxHeights[$columnIndex] = $maxSum / $this->gaussian->weightSum1D;
			}
		}

		return [$minHeights, $maxHeights];
	}
}
