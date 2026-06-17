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

namespace pocketmine\world\portal;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\math\Axis;
use pocketmine\world\Dimension;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\end\End;
use pocketmine\world\generator\hell\Nether;
use pocketmine\world\generator\normal\Normal;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;
use function floor;
use function max;
use function min;
use function str_ends_with;
use function strlen;
use function substr;
use const PHP_FLOAT_MAX;

final class PortalTravelHelper{
	public const NETHER_WORLD_SUFFIX = "_nether";
	public const END_WORLD_SUFFIX = "_the_end";

	public const PORTAL_COOLDOWN_TICKS = 300;
	private const PORTAL_SEARCH_RADIUS = 16;

	private function __construct(){
	}

	public static function travelThroughNetherPortal(Entity $entity) : void{
		$sourceWorld = $entity->getWorld();
		$sourceDimension = $sourceWorld->getDimension();
		if($sourceDimension === Dimension::THE_END){
			return;
		}
		$targetDimension = $sourceDimension === Dimension::NETHER ? Dimension::OVERWORLD : Dimension::NETHER;
		$targetWorld = self::resolveDimensionWorld($sourceWorld, $targetDimension);
		if($targetWorld === null){
			return;
		}

		$entity->setPortalCooldown(self::PORTAL_COOLDOWN_TICKS);

		$position = $entity->getPosition();
		$scale = $sourceDimension->getCoordinateScale() / $targetDimension->getCoordinateScale();
		$targetX = (int) floor($position->x * $scale);
		$targetZ = (int) floor($position->z * $scale);
		$targetY = $targetDimension === Dimension::NETHER ?
			(int) max(8, min(118, $position->y)) :
			(int) max($targetWorld->getMinY() + 4, min($targetWorld->getMaxY() - 8, $position->y));

		$targetWorld->orderChunkPopulation($targetX >> Chunk::COORD_BIT_SIZE, $targetZ >> Chunk::COORD_BIT_SIZE, null)->onCompletion(
			static function() use ($entity, $targetWorld, $targetX, $targetY, $targetZ) : void{
				if($entity->isClosed() || $entity->isFlaggedForDespawn() || !$targetWorld->isLoaded()){
					return;
				}
				$destination = self::findOrCreateNetherPortal($targetWorld, $targetX, $targetY, $targetZ);
				$entity->teleport($destination);
			},
			static function() : void{
				//world was unloaded or generation failed; the entity simply stays where it is
			}
		);
	}

	/**
	 * Starts generating the nether-portal destination chunk without teleporting, called the moment an entity enters a
	 * portal. By the time the entry animation finishes and {@link self::travelThroughNetherPortal} runs, the chunk is
	 * already populated, so the teleport happens instantly instead of stalling on generation.
	 */
	public static function warmUpNetherPortal(Entity $entity) : void{
		$sourceWorld = $entity->getWorld();
		$sourceDimension = $sourceWorld->getDimension();
		if($sourceDimension === Dimension::THE_END){
			return;
		}
		$targetDimension = $sourceDimension === Dimension::NETHER ? Dimension::OVERWORLD : Dimension::NETHER;
		$targetWorld = self::resolveDimensionWorld($sourceWorld, $targetDimension);
		if($targetWorld === null){
			return;
		}

		$position = $entity->getPosition();
		$scale = $sourceDimension->getCoordinateScale() / $targetDimension->getCoordinateScale();
		$targetX = (int) floor($position->x * $scale);
		$targetZ = (int) floor($position->z * $scale);
		//fire-and-forget: just warm the chunk up; travelThroughNetherPortal() will find it ready and teleport at once
		$targetWorld->orderChunkPopulation($targetX >> Chunk::COORD_BIT_SIZE, $targetZ >> Chunk::COORD_BIT_SIZE, null);
	}

	public static function travelThroughEndPortal(Entity $entity) : void{
		$sourceWorld = $entity->getWorld();
		if($sourceWorld->getDimension() === Dimension::THE_END){
			$targetWorld = self::resolveDimensionWorld($sourceWorld, Dimension::OVERWORLD);
			if($targetWorld === null){
				return;
			}
			$entity->setPortalCooldown(self::PORTAL_COOLDOWN_TICKS);
			$spawn = $targetWorld->getSpawnLocation();
			$targetWorld->orderChunkPopulation($spawn->getFloorX() >> Chunk::COORD_BIT_SIZE, $spawn->getFloorZ() >> Chunk::COORD_BIT_SIZE, null)->onCompletion(
				static function() use ($entity, $targetWorld) : void{
					if($entity->isClosed() || $entity->isFlaggedForDespawn() || !$targetWorld->isLoaded()){
						return;
					}
					$entity->teleport($targetWorld->getSpawnLocation());
				},
				static function() : void{
					//no-op
				}
			);
			return;
		}

		$targetWorld = self::resolveDimensionWorld($sourceWorld, Dimension::THE_END);
		if($targetWorld === null){
			return;
		}
		$entity->setPortalCooldown(self::PORTAL_COOLDOWN_TICKS);
		$platformX = End::SPAWN_PLATFORM_X;
		$platformY = End::SPAWN_PLATFORM_Y;
		$platformZ = End::SPAWN_PLATFORM_Z;
		$targetWorld->orderChunkPopulation($platformX >> Chunk::COORD_BIT_SIZE, $platformZ >> Chunk::COORD_BIT_SIZE, null)->onCompletion(
			static function() use ($entity, $targetWorld, $platformX, $platformY, $platformZ) : void{
				if($entity->isClosed() || $entity->isFlaggedForDespawn() || !$targetWorld->isLoaded()){
					return;
				}
				self::rebuildEndSpawnPlatform($targetWorld, $platformX, $platformY, $platformZ);
				$entity->teleport(new Position($platformX + 0.5, $platformY + 1, $platformZ + 0.5, $targetWorld));
			},
			static function() : void{
				//no-op
			}
		);
	}

	public static function resolveDimensionWorld(World $from, Dimension $target) : ?World{
		$manager = $from->getServer()->getWorldManager();

		$baseName = $from->getFolderName();
		foreach([self::NETHER_WORLD_SUFFIX, self::END_WORLD_SUFFIX] as $suffix){
			if(str_ends_with($baseName, $suffix)){
				$baseName = substr($baseName, 0, -strlen($suffix));
				break;
			}
		}
		if($baseName === ""){
			return null;
		}

		$targetName = match($target){
			Dimension::OVERWORLD => $baseName,
			Dimension::NETHER => $baseName . self::NETHER_WORLD_SUFFIX,
			Dimension::THE_END => $baseName . self::END_WORLD_SUFFIX
		};

		$world = $manager->getWorldByName($targetName);
		if($world !== null){
			return $world;
		}
		if($manager->loadWorld($targetName)){
			return $manager->getWorldByName($targetName);
		}

		$options = WorldCreationOptions::create()
			->setGeneratorClass(match($target){
				Dimension::OVERWORLD => Normal::class,
				Dimension::NETHER => Nether::class,
				Dimension::THE_END => End::class
			})
			->setSeed($from->getSeed());
		if(!$manager->generateWorld($targetName, $options)){
			return null;
		}
		return $manager->getWorldByName($targetName);
	}

	private static function findOrCreateNetherPortal(World $world, int $x, int $y, int $z) : Position{
		$portal = self::findNetherPortal($world, $x, $y, $z) ?? self::createNetherPortal($world, $x, $y, $z);
		//never drop the player inside the portal blocks/obsidian - they get wedged and can't move until they're forced
		//out. Step them onto a safe spot just outside the portal instead.
		return self::safeExitNear($world, $portal->getFloorX(), $portal->getFloorY(), $portal->getFloorZ());
	}

	/**
	 * Given any portal block, returns a safe standing position just outside the portal: a cell with two clear blocks over
	 * a solid floor, around the portal's base. Falls back to the portal base if nothing better is found.
	 */
	private static function safeExitNear(World $world, int $x, int $y, int $z) : Position{
		//drop to the bottom of the portal column so the player exits at its base, not wedged up in the frame
		while($y > $world->getMinY() + 2 && self::isPortalBlock($world, $x, $y - 1, $z)){
			--$y;
		}
		//the obsidian frame's bottom can sit a block above the surrounding floor, so check the base level and one below
		foreach([[0, 1], [0, -1], [1, 0], [-1, 0], [1, 1], [-1, -1], [1, -1], [-1, 1]] as [$dx, $dz]){
			foreach([0, -1] as $dy){
				if(self::isStandable($world, $x + $dx, $y + $dy, $z + $dz)){
					return new Position($x + $dx + 0.5, $y + $dy, $z + $dz + 0.5, $world);
				}
			}
		}
		return new Position($x + 0.5, $y, $z + 0.5, $world);
	}

	private static function isPortalBlock(World $world, int $x, int $y, int $z) : bool{
		return $world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::NETHER_PORTAL;
	}

	private static function isStandable(World $world, int $x, int $y, int $z) : bool{
		return $world->getBlockAt($x, $y - 1, $z)->isSolid()
			&& $world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::AIR
			&& $world->getBlockAt($x, $y + 1, $z)->getTypeId() === BlockTypeIds::AIR;
	}

	private static function findNetherPortal(World $world, int $x, int $y, int $z) : ?Position{
		$portalStateIds = [
			VanillaBlocks::NETHER_PORTAL()->setAxis(Axis::X)->getStateId() => true,
			VanillaBlocks::NETHER_PORTAL()->setAxis(Axis::Z)->getStateId() => true
		];

		$minChunkX = ($x - self::PORTAL_SEARCH_RADIUS) >> Chunk::COORD_BIT_SIZE;
		$maxChunkX = ($x + self::PORTAL_SEARCH_RADIUS) >> Chunk::COORD_BIT_SIZE;
		$minChunkZ = ($z - self::PORTAL_SEARCH_RADIUS) >> Chunk::COORD_BIT_SIZE;
		$maxChunkZ = ($z + self::PORTAL_SEARCH_RADIUS) >> Chunk::COORD_BIT_SIZE;

		$best = null;
		$bestDistanceSq = PHP_FLOAT_MAX;

		for($chunkX = $minChunkX; $chunkX <= $maxChunkX; ++$chunkX){
			for($chunkZ = $minChunkZ; $chunkZ <= $maxChunkZ; ++$chunkZ){
				$chunk = $world->getChunk($chunkX, $chunkZ);
				if($chunk === null){
					continue;
				}
				foreach($chunk->getSubChunks() as $subChunkY => $subChunk){
					if($subChunk->isEmptyFast()){
						continue;
					}
					$containsPortal = false;
					foreach($subChunk->getBlockLayers() as $layer){
						foreach($layer->getPalette() as $stateId){
							if(isset($portalStateIds[$stateId])){
								$containsPortal = true;
								break 2;
							}
						}
					}
					if(!$containsPortal){
						continue;
					}
					$baseX = $chunkX << Chunk::COORD_BIT_SIZE;
					$baseY = $subChunkY << Chunk::COORD_BIT_SIZE;
					$baseZ = $chunkZ << Chunk::COORD_BIT_SIZE;
					for($localX = 0; $localX < Chunk::EDGE_LENGTH; ++$localX){
						for($localZ = 0; $localZ < Chunk::EDGE_LENGTH; ++$localZ){
							for($localY = 0; $localY < Chunk::EDGE_LENGTH; ++$localY){
								if(isset($portalStateIds[$subChunk->getBlockStateId($localX, $localY, $localZ)])){
									$worldX = $baseX + $localX;
									$worldY = $baseY + $localY;
									$worldZ = $baseZ + $localZ;
									$distanceSq = ($worldX - $x) ** 2 + ($worldY - $y) ** 2 + ($worldZ - $z) ** 2;
									if($distanceSq < $bestDistanceSq){
										$bestDistanceSq = $distanceSq;
										$best = new Position($worldX + 0.5, $worldY, $worldZ + 0.5, $world);
									}
								}
							}
						}
					}
				}
			}
		}

		return $best;
	}

	private static function createNetherPortal(World $world, int $x, int $y, int $z) : Position{
		$x = ($x & ~Chunk::COORD_MASK) + max(4, min(10, $x & Chunk::COORD_MASK));
		$z = ($z & ~Chunk::COORD_MASK) + max(4, min(10, $z & Chunk::COORD_MASK));
		$y = max($world->getMinY() + 4, min($world->getMaxY() - 8, $y));

		$floorY = self::findPortalFloor($world, $x, $y, $z);

		$air = VanillaBlocks::AIR();
		$obsidian = VanillaBlocks::OBSIDIAN();
		$portal = VanillaBlocks::NETHER_PORTAL()->setAxis(Axis::X);

		//carve a generous open chamber with a solid obsidian floor so the arriving player can stand and step out instead of
		//being boxed into the netherrack around a tiny portal hole
		for($ox = -2; $ox <= 3; ++$ox){
			for($oz = -2; $oz <= 2; ++$oz){
				$world->setBlockAt($x + $ox, $floorY - 1, $z + $oz, $obsidian, false);
				for($oy = 0; $oy <= 4; ++$oy){
					$world->setBlockAt($x + $ox, $floorY + $oy, $z + $oz, $air, false);
				}
			}
		}

		//the 4-wide x 5-tall obsidian frame with the portal surface inside it, along the X axis at this Z
		for($i = -1; $i <= 2; ++$i){
			for($h = 0; $h < 5; ++$h){
				if($i === -1 || $i === 2 || $h === 0 || $h === 4){
					$world->setBlockAt($x + $i, $floorY + $h, $z, $obsidian, false);
				}else{
					$world->setBlockAt($x + $i, $floorY + $h, $z, $portal, false);
				}
			}
		}

		//return one of the portal's own blocks; findOrCreateNetherPortal() resolves a safe standing spot beside it
		return new Position($x, $floorY + 1, $z, $world);
	}

	private static function findPortalFloor(World $world, int $x, int $y, int $z) : int{
		$maxY = $world->getDimension() === Dimension::NETHER ? 118 : $world->getMaxY() - 8;
		$minY = $world->getDimension() === Dimension::NETHER ? 8 : $world->getMinY() + 4;

		for($candidate = $y; $candidate <= $maxY; ++$candidate){
			if(self::isValidPortalFloor($world, $x, $candidate, $z)){
				return $candidate;
			}
		}
		for($candidate = $y - 1; $candidate >= $minY; --$candidate){
			if(self::isValidPortalFloor($world, $x, $candidate, $z)){
				return $candidate;
			}
		}
		return max($minY, min($maxY, $y));
	}

	private static function isValidPortalFloor(World $world, int $x, int $y, int $z) : bool{
		if(!$world->getBlockAt($x, $y - 1, $z)->isSolid()){
			return false;
		}
		for($oy = 0; $oy < 5; ++$oy){
			if($world->getBlockAt($x, $y + $oy, $z)->getTypeId() !== BlockTypeIds::AIR){
				return false;
			}
		}
		return true;
	}

	private static function rebuildEndSpawnPlatform(World $world, int $centerX, int $platformY, int $centerZ) : void{
		$obsidian = VanillaBlocks::OBSIDIAN();
		$air = VanillaBlocks::AIR();
		for($x = $centerX - End::SPAWN_PLATFORM_RADIUS; $x <= $centerX + End::SPAWN_PLATFORM_RADIUS; ++$x){
			for($z = $centerZ - End::SPAWN_PLATFORM_RADIUS; $z <= $centerZ + End::SPAWN_PLATFORM_RADIUS; ++$z){
				$world->setBlockAt($x, $platformY, $z, $obsidian, false);
				for($y = $platformY + 1; $y <= $platformY + 3; ++$y){
					$world->setBlockAt($x, $y, $z, $air, false);
				}
			}
		}
	}
}
