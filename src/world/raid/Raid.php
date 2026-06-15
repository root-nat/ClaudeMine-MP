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

namespace pocketmine\world\raid;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Evoker;
use pocketmine\entity\Location;
use pocketmine\entity\Monster;
use pocketmine\entity\Pillager;
use pocketmine\entity\Ravager;
use pocketmine\entity\Villager;
use pocketmine\entity\Vindicator;
use pocketmine\entity\Witch;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;
use pocketmine\world\ChunkLoader;
use pocketmine\world\World;
use function abs;
use function cos;
use function count;
use function floor;
use function max;
use function mt_rand;
use function sin;
use const M_PI;

/**
 * A single active raid centred on a village: it spawns successive waves of raiders ({@link RaidWaveComposition}) until
 * they are all defeated (victory, rewarding nearby defenders with Hero of the Village) or the defenders abandon it for
 * too long (it dissipates). The wave decision itself lives in the pure {@link RaidProgress}; this class does the live
 * spawning, defender tracking and effects. Driven once per second by {@link RaidManager}.
 */
final class Raid implements ChunkLoader{

	/** Raiders erupt on a ring within this many blocks of the centre. */
	private const SPAWN_RADIUS = 12.0;
	/** A raider's resolved ground must be within this many blocks of the centre's Y, else the spot is rejected. */
	private const SPAWN_Y_BAND = 8;
	/** Chunk radius around the centre kept loaded for the raid's lifetime (so raiders aren't culled by a chunk unload). */
	private const CHUNK_RADIUS = 1;
	/** Defenders within this of the centre count as present (and earn the victory reward). */
	private const DEFENDER_RADIUS = 48.0;
	/** Raid ticks (~seconds) with no defender nearby before the raid is abandoned. */
	private const MAX_IDLE_TICKS = 120;
	/** Hero of the Village duration on victory (40 minutes, vanilla). */
	private const HERO_DURATION_TICKS = 48000;

	private int $difficulty;
	private int $totalWaves;
	private int $spawnedWaves = 0;
	private int $idleTicks = 0;
	/** @var int[] entity IDs of this raid's still-living raiders */
	private array $raiderIds = [];
	private bool $finished = false;
	/** Becomes true once the raid has seen any villager, so their later extinction can be detected as a loss. */
	private bool $everHadVillagers = false;
	private int $centerChunkX;
	private int $centerChunkZ;

	public function __construct(
		private World $world,
		private Vector3 $center,
		private int $badOmenLevel
	){
		$this->difficulty = $world->getDifficulty();
		$this->totalWaves = RaidWaveComposition::waveCount($this->difficulty);
		$this->centerChunkX = $center->getFloorX() >> 4;
		$this->centerChunkZ = $center->getFloorZ() >> 4;
		//pin the surrounding chunks for the raid's lifetime: a chunk unload would close() the raiders, which
		//countAliveRaiders would then mis-read as "wave cleared" and chain the next wave into thin air
		for($dx = -self::CHUNK_RADIUS; $dx <= self::CHUNK_RADIUS; ++$dx){
			for($dz = -self::CHUNK_RADIUS; $dz <= self::CHUNK_RADIUS; ++$dz){
				$world->registerChunkLoader($this, $this->centerChunkX + $dx, $this->centerChunkZ + $dz, true);
			}
		}
	}

	public function getCenter() : Vector3{
		return $this->center;
	}

	public function isFinished() : bool{
		return $this->finished;
	}

	/**
	 * Ends the raid exactly once, releasing the chunk pins so the area can unload normally again.
	 */
	private function markFinished() : void{
		if($this->finished){
			return;
		}
		$this->finished = true;
		for($dx = -self::CHUNK_RADIUS; $dx <= self::CHUNK_RADIUS; ++$dx){
			for($dz = -self::CHUNK_RADIUS; $dz <= self::CHUNK_RADIUS; ++$dz){
				$this->world->unregisterChunkLoader($this, $this->centerChunkX + $dx, $this->centerChunkZ + $dz);
			}
		}
	}

	/**
	 * Advances the raid one raid-tick (the manager calls this about once per second).
	 */
	public function tick() : void{
		if($this->finished){
			return;
		}
		if($this->totalWaves <= 0){ //peaceful difficulty: nothing to raid
			$this->markFinished();
			return;
		}

		$alive = $this->countAliveRaiders();
		$hasDefender = $this->hasDefendersNearby();
		$this->idleTicks = $hasDefender ? 0 : $this->idleTicks + 1;

		//track the village: once we've seen villagers, their total extinction means the raiders won
		$villagers = $this->countVillagers();
		if($villagers > 0){
			$this->everHadVillagers = true;
		}
		$villageFallen = $this->everHadVillagers && $villagers === 0;

		switch(RaidProgress::decide($this->spawnedWaves, $this->totalWaves, $alive, $this->idleTicks, self::MAX_IDLE_TICKS, $hasDefender, $villageFallen)){
			case RaidAction::WAIT:
				break;
			case RaidAction::SPAWN_WAVE:
				$this->spawnNextWave();
				break;
			case RaidAction::VICTORY:
				$this->onVictory();
				break;
			case RaidAction::DEFEAT:
				if($villageFallen){
					$this->announce(TextFormat::DARK_RED . "Raid", TextFormat::RED . "Le village est tombé");
				}
				$this->markFinished();
				break;
		}
	}

	private function countAliveRaiders() : int{
		$stillAlive = [];
		foreach($this->raiderIds as $id){
			$entity = $this->world->getEntity($id);
			if($entity !== null && $entity->isAlive() && !$entity->isFlaggedForDespawn()){
				$stillAlive[] = $id;
			}
		}
		$this->raiderIds = $stillAlive;
		return count($stillAlive);
	}

	private function hasDefendersNearby() : bool{
		foreach($this->world->getPlayers() as $player){
			if($player->isAlive() && $player->getPosition()->distanceSquared($this->center) <= self::DEFENDER_RADIUS ** 2){
				return true;
			}
		}
		return false;
	}

	private function countVillagers() : int{
		$r = self::DEFENDER_RADIUS;
		$box = new AxisAlignedBB(
			$this->center->x - $r, $this->center->y - $r, $this->center->z - $r,
			$this->center->x + $r, $this->center->y + $r, $this->center->z + $r
		);
		$count = 0;
		foreach($this->world->getNearbyEntities($box) as $entity){
			if($entity instanceof Villager && $entity->isAlive()){
				++$count;
			}
		}
		return $count;
	}

	private function spawnNextWave() : void{
		++$this->spawnedWaves;
		$wave = $this->spawnedWaves;
		foreach(RaiderType::cases() as $type){
			$count = RaidWaveComposition::totalCount($type, $wave, $this->difficulty, $this->badOmenLevel);
			for($i = 0; $i < $count; ++$i){
				$raider = $this->createRaider($type, $this->randomSpawnLocation());
				$raider->setPersistent(); //a raid member must not be culled by the distance despawn mid-fight
				$raider->spawnToAll();
				$this->raiderIds[] = $raider->getId();
			}
		}
		$this->announce(TextFormat::DARK_RED . "Raid", TextFormat::RED . "Vague " . $wave . " / " . $this->totalWaves);
	}

	private function randomSpawnLocation() : Location{
		//try a few points on the ring and land each raider on real ground near the centre's height; this stops raiders
		//from erupting over open void off the edge of a small skyblock island (where they would just fall and die)
		for($attempt = 0; $attempt < 10; ++$attempt){
			$angle = (mt_rand(0, 359) / 180.0) * M_PI;
			$dist = self::SPAWN_RADIUS * (0.4 + (mt_rand(0, 60) / 100.0)); //~5..12 blocks out
			$x = $this->center->x + cos($angle) * $dist;
			$z = $this->center->z + sin($angle) * $dist;
			$highest = $this->world->getHighestBlockAt((int) floor($x), (int) floor($z));
			if($highest !== null && abs(($highest + 1) - $this->center->y) <= self::SPAWN_Y_BAND){
				return Location::fromObject(new Vector3($x, $highest + 1, $z), $this->world);
			}
		}
		//no solid ground on the ring (tiny island over void): drop the raider on the centre, beside the defender
		return Location::fromObject($this->center, $this->world);
	}

	private function createRaider(RaiderType $type, Location $location) : Monster{
		return match($type){
			RaiderType::PILLAGER => new Pillager($location),
			RaiderType::VINDICATOR => new Vindicator($location),
			RaiderType::WITCH => new Witch($location),
			RaiderType::RAVAGER => new Ravager($location),
			RaiderType::EVOKER => new Evoker($location),
		};
	}

	private function onVictory() : void{
		$this->markFinished();
		$heroLevel = max(0, $this->badOmenLevel - 1);
		foreach($this->world->getPlayers() as $player){
			if($player->isAlive() && $player->getPosition()->distanceSquared($this->center) <= self::DEFENDER_RADIUS ** 2){
				$player->getEffects()->add(new EffectInstance(VanillaEffects::VILLAGE_HERO(), self::HERO_DURATION_TICKS, $heroLevel));
				$player->sendTitle(TextFormat::GOLD . "Raid repoussé !", TextFormat::YELLOW . "Héros du village");
			}
		}
	}

	private function announce(string $title, string $subtitle) : void{
		foreach($this->world->getPlayers() as $player){
			if($player->getPosition()->distanceSquared($this->center) <= self::DEFENDER_RADIUS ** 2){
				$player->sendTitle($title, $subtitle);
			}
		}
	}
}
