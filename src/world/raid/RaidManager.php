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

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Villager;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;

/**
 * Per-world raid orchestrator: each tick it scans for a player carrying Bad Omen who has entered a village (defined here,
 * for non-vanilla terrain like skyblock, simply as being near at least {@link self::MIN_VILLAGERS} villagers), consumes
 * their Bad Omen and starts a {@link Raid} there, then advances any active raids. Lightweight - the scans are throttled
 * and only do work when a Bad-Omen player is online. Not persisted: an in-progress raid is forgotten across a restart.
 */
final class RaidManager{

	/** Server ticks between scans for a new raid trigger. */
	private const TRIGGER_INTERVAL = 40;
	/** Server ticks between advancing active raids (raids reason in ~1s steps). */
	private const RAID_TICK_INTERVAL = 20;
	/** A Bad-Omen player counts as "in a village" if this many villagers are within VILLAGE_RADIUS. */
	private const MIN_VILLAGERS = 1;
	private const VILLAGE_RADIUS = 32.0;
	/** A new raid won't start this close to an existing one (avoids stacking raids on one village). */
	private const DEDUPE_RADIUS = 64.0;

	private int $triggerCounter = 0;
	private int $raidCounter = 0;
	/** @var Raid[] */
	private array $raids = [];

	public function __construct(
		private World $world
	){}

	public function tick() : void{
		if(++$this->triggerCounter >= self::TRIGGER_INTERVAL){
			$this->triggerCounter = 0;
			$this->checkTriggers();
		}
		if(++$this->raidCounter >= self::RAID_TICK_INTERVAL){
			$this->raidCounter = 0;
			$active = [];
			foreach($this->raids as $raid){
				$raid->tick();
				if(!$raid->isFinished()){
					$active[] = $raid;
				}
			}
			$this->raids = $active;
		}
	}

	/**
	 * Returns the active raids in this world.
	 *
	 * @return Raid[]
	 */
	public function getRaids() : array{
		return $this->raids;
	}

	private function checkTriggers() : void{
		foreach($this->world->getPlayers() as $player){
			if(!$player->isAlive()){
				continue;
			}
			$effects = $player->getEffects();
			if(!$effects->has(VanillaEffects::BAD_OMEN())){
				continue;
			}
			$center = $player->getPosition()->asVector3();
			if($this->countVillagersNear($center) < self::MIN_VILLAGERS){
				continue; //not at a village yet - keep the omen until they reach one (vanilla)
			}
			if(RaidWaveComposition::waveCount($this->world->getDifficulty()) <= 0){
				continue; //peaceful: no raid, and like vanilla the omen is NOT consumed
			}

			//at a village on a raiding difficulty: spend the omen here, exactly once (consume before the dedupe check so a
			//player standing inside an active raid can't keep their omen and chain a second raid the moment it ends)
			$badOmen = $effects->get(VanillaEffects::BAD_OMEN());
			$level = $badOmen !== null ? $badOmen->getEffectLevel() : 1;
			$effects->remove(VanillaEffects::BAD_OMEN());

			if($this->getRaidNear($center) !== null){
				continue; //this village is already under raid; the omen is spent but no second raid starts
			}
			$this->raids[] = new Raid($this->world, $center, $level);
			$player->sendTitle(TextFormat::DARK_RED . "Raid !", TextFormat::RED . "Le village est attaqué");
		}
	}

	private function getRaidNear(Vector3 $pos) : ?Raid{
		foreach($this->raids as $raid){
			if(!$raid->isFinished() && $raid->getCenter()->distanceSquared($pos) <= self::DEDUPE_RADIUS ** 2){
				return $raid;
			}
		}
		return null;
	}

	private function countVillagersNear(Vector3 $center) : int{
		$bb = new AxisAlignedBB(
			$center->x - self::VILLAGE_RADIUS, $center->y - self::VILLAGE_RADIUS, $center->z - self::VILLAGE_RADIUS,
			$center->x + self::VILLAGE_RADIUS, $center->y + self::VILLAGE_RADIUS, $center->z + self::VILLAGE_RADIUS
		);
		$count = 0;
		foreach($this->world->getNearbyEntities($bb) as $entity){
			if($entity instanceof Villager && $entity->isAlive()){
				++$count;
			}
		}
		return $count;
	}
}
