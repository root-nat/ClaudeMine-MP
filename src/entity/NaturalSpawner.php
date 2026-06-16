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

namespace pocketmine\entity;

use pocketmine\block\Block;
use pocketmine\block\Grass;
use pocketmine\block\Lava;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\world\Dimension;
use pocketmine\world\World;
use function mt_rand;

/**
 * Bounded natural mob spawning, attempted at a low rate per ticking chunk (which are already near players). It samples a
 * column, checks the surface, light and per-area population cap, then spawns a small pack of hostiles (in darkness) or
 * passive animals (on grass in light). The decisions come from {@link MobSpawnRules}; this only resolves the world state
 * and creates the entities. Strict caps keep it from flooding or lagging.
 */
final class NaturalSpawner{

	private function __construct(){
		//NOOP
	}

	public static function attempt(World $world, int $chunkX, int $chunkZ) : void{
		if($world->getDimension() === Dimension::NETHER){
			//the Nether has no sky surface (a bedrock ceiling) and its own roster, so it spawns differently
			self::attemptNether($world, $chunkX, $chunkZ);
			return;
		}

		$x = ($chunkX << 4) + mt_rand(0, 15);
		$z = ($chunkZ << 4) + mt_rand(0, 15);

		$surface = self::findSpawnSurface($world, $x, $z);
		if($surface === null){
			return;
		}
		[$spawnY, $ground] = $surface;

		$light = $world->getFullLightAt($x, $spawnY, $z);
		$centre = new Vector3($x + 0.5, $spawnY, $z + 0.5);

		if($ground instanceof Grass && MobSpawnRules::canPassiveSpawnAt($light)){
			self::spawnPack($world, $centre, false);
		}elseif(MobSpawnRules::canHostileSpawnAt($light)){
			//throttle hostiles harder than animals so nights don't flood with monsters
			if(mt_rand(0, MobSpawnRules::HOSTILE_ATTEMPT_DENOM - 1) !== 0){
				return;
			}
			self::spawnPack($world, $centre, true);
		}
	}

	/**
	 * Finds the real ground surface in a column: the highest OPAQUE solid block (skipping transparent blocks like tree
	 * leaves and glass) that has two clear blocks above it. Returns [feetY, groundBlock] or null. This stops mobs from
	 * spawning on treetops or other floating perches where they would be stuck.
	 *
	 * @return array{int, Block}|null
	 */
	public static function findSpawnSurface(World $world, int $x, int $z) : ?array{
		$top = $world->getHighestBlockAt($x, $z);
		if($top === null){
			return null;
		}
		$minY = $world->getMinY();
		for($y = $top; $y >= $minY; --$y){
			$block = $world->getBlockAt($x, $y, $z);
			if(!$block->isSolid() || $block->isTransparent()){
				continue; //air, leaves, glass, plants... keep descending to the real ground
			}
			//topmost opaque ground block: usable only with two clear blocks above for the mob to fit
			if($world->getBlockAt($x, $y + 1, $z)->isSolid() || $world->getBlockAt($x, $y + 2, $z)->isSolid()){
				return null;
			}
			return [$y + 1, $block];
		}
		return null;
	}

	private static function spawnPack(World $world, Vector3 $centre, bool $hostile) : void{
		$cap = $hostile ? MobSpawnRules::HOSTILE_CAP : MobSpawnRules::PASSIVE_CAP;
		if(!MobSpawnRules::isUnderCap(self::countNearby($world, $centre, $hostile), $cap)){
			return;
		}

		$pack = MobSpawnRules::packSize(mt_rand(0, 1024), $hostile ? MobSpawnRules::MAX_HOSTILE_PACK : MobSpawnRules::MAX_PASSIVE_PACK);
		for($i = 0; $i < $pack; ++$i){
			$px = (int) $centre->x + mt_rand(-2, 2);
			$pz = (int) $centre->z + mt_rand(-2, 2);
			//resolve the ground for each pack member so none spawn floating over uneven terrain
			$surface = self::findSpawnSurface($world, $px, $pz);
			if($surface === null){
				continue;
			}
			$mob = self::createMob($world, new Vector3($px + 0.5, $surface[0], $pz + 0.5), $hostile);
			$mob->spawnToAll();
		}
	}

	private static function countNearby(World $world, Vector3 $centre, bool $hostile) : int{
		$r = MobSpawnRules::CAP_RADIUS;
		$box = new AxisAlignedBB($centre->x - $r, $centre->y - $r, $centre->z - $r, $centre->x + $r, $centre->y + $r, $centre->z + $r);
		$count = 0;
		foreach($world->getNearbyEntities($box) as $entity){
			if($hostile ? $entity instanceof Monster : $entity instanceof Animal){
				++$count;
			}
		}
		return $count;
	}

	private static function createMob(World $world, Vector3 $pos, bool $hostile) : Living{
		$location = Location::fromObject($pos, $world);
		if($hostile){
			return match(mt_rand(0, 3)){
				0 => mt_rand(0, 4) === 0 ? new Husk($location) : new Zombie($location),
				1 => mt_rand(0, 4) === 0 ? new Stray($location) : new Skeleton($location),
				2 => new Creeper($location),
				default => new Spider($location),
			};
		}
		return match(mt_rand(0, 3)){
			0 => new Cow($location),
			1 => new Pig($location),
			2 => new Sheep($location),
			default => new Chicken($location),
		};
	}

	private static function attemptNether(World $world, int $chunkX, int $chunkZ) : void{
		$x = ($chunkX << 4) + mt_rand(0, 15);
		$z = ($chunkZ << 4) + mt_rand(0, 15);

		$floor = self::findNetherSpawnFloor($world, $x, $z);
		if($floor === null){
			return;
		}
		[$y] = $floor;
		$centre = new Vector3($x + 0.5, $y, $z + 0.5);
		//the Nether roster is a hostile/neutral mix, so it all shares one (slightly higher) cap so caverns don't flood
		if(!MobSpawnRules::isUnderCap(self::countNetherMobs($world, $centre), MobSpawnRules::NETHER_MOB_CAP)){
			return;
		}

		$pack = MobSpawnRules::packSize(mt_rand(0, 1024), MobSpawnRules::MAX_HOSTILE_PACK);
		for($i = 0; $i < $pack; ++$i){
			$px = $x + mt_rand(-2, 2);
			$pz = $z + mt_rand(-2, 2);
			$spot = self::findNetherSpawnFloor($world, $px, $pz);
			if($spot === null){
				continue;
			}
			[$sy, $onLava, $tall] = $spot;
			$mob = self::createNetherMob($world, new Vector3($px + 0.5, $sy, $pz + 0.5), $world->getBiomeId($px, $sy, $pz), $onLava, $tall);
			$mob->spawnToAll();
		}
	}

	/**
	 * Samples a few random heights for a Nether-spawnable cell: two clear blocks with either a solid floor (land mobs) or
	 * a lava surface (striders) below. Returns [feetY, onLava, tall] - tall meaning there's headroom for a 4-block Ghast -
	 * or null. Unlike the Overworld there is no single surface; mobs spawn throughout the open caverns.
	 *
	 * @return array{int, bool, bool}|null
	 */
	private static function findNetherSpawnFloor(World $world, int $x, int $z) : ?array{
		if(!$world->isChunkLoaded($x >> 4, $z >> 4)){
			return null; //an ungenerated column reads as air and would give a phantom floor
		}
		for($attempt = 0; $attempt < 8; ++$attempt){
			$y = 4 + mt_rand(0, 114); //4..118, a safe margin inside the Nether's 1..126 playable band
			if($world->getBlockAt($x, $y, $z)->isSolid() || $world->getBlockAt($x, $y + 1, $z)->isSolid()){
				continue; //no room to stand
			}
			$below = $world->getBlockAt($x, $y - 1, $z);
			if($below instanceof Lava){
				$onLava = true;
			}elseif($below->isSolid()){
				$onLava = false;
			}else{
				continue; //nothing underfoot - over the void
			}
			$tall = !$world->getBlockAt($x, $y + 2, $z)->isSolid() && !$world->getBlockAt($x, $y + 3, $z)->isSolid();
			return [$y, $onLava, $tall];
		}
		return null;
	}

	private static function createNetherMob(World $world, Vector3 $pos, int $biome, bool $onLava, bool $tall) : Living{
		$location = Location::fromObject($pos, $world);
		if($onLava){
			return new Strider($location); //only striders walk on the lava seas
		}
		$roll = mt_rand(0, 99);
		//Ghasts are 4 blocks tall - only let them take a slot where there's headroom ($tall), else a smaller biome mob does
		return match($biome){
			BiomeIds::CRIMSON_FOREST => $roll < 40 ? new ZombifiedPiglin($location) : ($roll < 70 ? new Hoglin($location) : new Piglin($location)),
			BiomeIds::WARPED_FOREST => new Enderman($location), //vanilla: the warped forest is the one safe biome - endermen only
			BiomeIds::SOULSAND_VALLEY => $roll < 50 ? new Skeleton($location) : ($roll < 80 && $tall ? new Ghast($location) : new Enderman($location)),
			BiomeIds::BASALT_DELTAS => $roll < 70 || !$tall ? new MagmaCube($location) : new Ghast($location),
			default => match(true){ //HELL / nether wastes
				$roll < 40 => new ZombifiedPiglin($location),
				$roll < 60 => new MagmaCube($location),
				$roll < 80 => new Skeleton($location),
				$roll < 92 && $tall => new Ghast($location),
				default => new Piglin($location),
			},
		};
	}

	private static function countNetherMobs(World $world, Vector3 $centre) : int{
		$r = MobSpawnRules::CAP_RADIUS;
		$box = new AxisAlignedBB($centre->x - $r, $centre->y - $r, $centre->z - $r, $centre->x + $r, $centre->y + $r, $centre->z + $r);
		$count = 0;
		foreach($world->getNearbyEntities($box) as $entity){
			if($entity instanceof Living && !($entity instanceof Human)){
				++$count;
			}
		}
		return $count;
	}
}
