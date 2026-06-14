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

namespace pocketmine\entity\ai\memory;

/**
 * Typed keys for the {@link Memory} store, mirroring Bedrock's brain memory modules. Each case documents the value type
 * it is expected to hold.
 */
enum MemoryModuleType : string{
	//value type: TargetCandidate[] - attackable candidates seen by sensors this scan
	case NEAREST_ENTITIES = "nearest_entities";
	//value type: TargetCandidate[] - nearby players seen by sensors this scan
	case NEAREST_PLAYERS = "nearest_players";
	//value type: TargetCandidate - the mob's current attack target
	case ATTACK_TARGET = "attack_target";
	//value type: TargetCandidate - the last entity that hurt this mob
	case HURT_BY = "hurt_by";
	//value type: Vector3 - a chosen walk destination
	case WALK_TARGET = "walk_target";
	//value type: int - tick at which the mob may attack again
	case ATTACK_COOLDOWN_UNTIL = "attack_cooldown_until";
	//value type: Vector3 - a remembered home / spawn point
	case HOME = "home";
	//value type: TargetCandidate - the nearest player holding this animal's food, tempting it to follow
	case TEMPTING_PLAYER = "tempting_player";
	//value type: TargetCandidate - the nearest in-love mate of the same species this animal should approach to breed
	case BREED_TARGET = "breed_target";
	//value type: TargetCandidate - the nearest adult of the same species a baby should follow
	case PARENT = "parent";
	//value type: TargetCandidate - the owner a tamed mob (e.g. a wolf) should follow
	case OWNER = "owner";
	//value type: TargetCandidate - the enemy a wolf has chosen to fight (retaliation or defending its owner)
	case COMBAT_TARGET = "combat_target";
	//value type: TargetCandidate - a nearby entity this mob is afraid of and should flee from (e.g. a creeper from a cat)
	case AVOID_TARGET = "avoid_target";
}
