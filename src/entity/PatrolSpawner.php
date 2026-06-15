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

use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use function mt_rand;

/**
 * Occasionally spawns an illager patrol - a banner-carrying {@link Pillager} captain plus a few more pillagers and
 * vindicators - on the surface near players. Killing the captain grants Bad Omen, which seeds a raid at the next village.
 * Far rarer than {@link NaturalSpawner}, and self-limiting (it won't add a patrol where raiders already roam) so captains
 * don't pile up. Reuses NaturalSpawner's ground resolution so members never spawn floating or on treetops.
 */
final class PatrolSpawner{

	private const PATROL_MIN_SIZE = 2;
	private const PATROL_MAX_SIZE = 4;
	/** A patrol won't spawn if at least this many raiders already roam within RAIDER_RADIUS of the nearby player. */
	private const NEARBY_RAIDER_CAP = 2;
	private const RAIDER_RADIUS = 96.0;
	/** Hard ceiling on simultaneously-loaded captains so the persistent banner-carriers can't accumulate over a session. */
	private const MAX_LIVE_CAPTAINS = 3;

	private function __construct(){
		//NOOP
	}

	public static function attempt(World $world, int $chunkX, int $chunkZ) : void{
		//hard ceiling first: persistent captains never distance-despawn, so without this they would pile up over a session
		if(self::countCaptains($world) >= self::MAX_LIVE_CAPTAINS){
			return;
		}

		$x = ($chunkX << 4) + mt_rand(0, 15);
		$z = ($chunkZ << 4) + mt_rand(0, 15);

		$surface = NaturalSpawner::findSpawnSurface($world, $x, $z);
		if($surface === null){
			return;
		}
		$centre = new Vector3($x + 0.5, $surface[0], $z + 0.5);

		//only spawn around an actual player, and only if that player isn't already swarmed - this anchors the cap to the
		//player (not the random candidate column) so patrols can't ring a player from every direction or spawn into the void
		$player = $world->getNearestEntity($centre, self::RAIDER_RADIUS, Player::class);
		if($player === null){
			return;
		}
		if(self::countNearbyRaiders($world, $player->getPosition()->asVector3()) >= self::NEARBY_RAIDER_CAP){
			return;
		}

		//the captain spawns on the sampled surface (already validated); the rest of the patrol gathers around it
		$captain = new Pillager(Location::fromObject($centre, $world));
		$captain->setCaptain();
		$captain->spawnToAll();

		$members = mt_rand(self::PATROL_MIN_SIZE, self::PATROL_MAX_SIZE) - 1;
		for($i = 0; $i < $members; ++$i){
			$mx = (int) $centre->x + mt_rand(-3, 3);
			$mz = (int) $centre->z + mt_rand(-3, 3);
			$memberSurface = NaturalSpawner::findSpawnSurface($world, $mx, $mz);
			if($memberSurface === null){
				continue; //no footing here - skip this member rather than float it over the void
			}
			$location = Location::fromObject(new Vector3($mx + 0.5, $memberSurface[0], $mz + 0.5), $world);
			$member = mt_rand(0, 3) === 0 ? new Vindicator($location) : new Pillager($location);
			$member->setPersistent(); //travel with the captain instead of evaporating the moment the player steps away
			$member->spawnToAll();
		}
	}

	private static function countCaptains(World $world) : int{
		$count = 0;
		foreach($world->getEntities() as $entity){
			if($entity instanceof Raider && $entity->isCaptain()){
				++$count;
			}
		}
		return $count;
	}

	private static function countNearbyRaiders(World $world, Vector3 $centre) : int{
		$r = self::RAIDER_RADIUS;
		$box = new AxisAlignedBB($centre->x - $r, $centre->y - $r, $centre->z - $r, $centre->x + $r, $centre->y + $r, $centre->z + $r);
		$count = 0;
		foreach($world->getNearbyEntities($box) as $entity){
			if($entity instanceof Raider){
				++$count;
			}
		}
		return $count;
	}
}
