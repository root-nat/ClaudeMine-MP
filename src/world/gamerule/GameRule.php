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

namespace pocketmine\world\gamerule;

enum GameRule : string{
	case COMMAND_BLOCK_OUTPUT = "commandblockoutput";
	case DO_DAYLIGHT_CYCLE = "dodaylightcycle";
	case DO_ENTITY_DROPS = "doentitydrops";
	case DO_FIRE_TICK = "dofiretick";
	case DO_IMMEDIATE_RESPAWN = "doimmediaterespawn";
	case DO_INSOMNIA = "doinsomnia";
	case DO_MOB_LOOT = "domobloot";
	case DO_MOB_SPAWNING = "domobspawning";
	case DO_TILE_DROPS = "dotiledrops";
	case DO_WEATHER_CYCLE = "doweathercycle";
	case DROWNING_DAMAGE = "drowningdamage";
	case FALL_DAMAGE = "falldamage";
	case FIRE_DAMAGE = "firedamage";
	case FREEZE_DAMAGE = "freezedamage";
	case KEEP_INVENTORY = "keepinventory";
	case LOCATOR_BAR = "locatorbar";
	case MOB_GRIEFING = "mobgriefing";
	case NATURAL_REGENERATION = "naturalregeneration";
	case PLAYERS_SLEEPING_PERCENTAGE = "playerssleepingpercentage";
	case PVP = "pvp";
	case RANDOM_TICK_SPEED = "randomtickspeed";
	case RESPAWN_BLOCKS_EXPLODE = "respawnblocksexplode";
	case SEND_COMMAND_FEEDBACK = "sendcommandfeedback";
	case SHOW_COORDINATES = "showcoordinates";
	case SHOW_DEATH_MESSAGES = "showdeathmessages";
	case SPAWN_RADIUS = "spawnradius";
	case TNT_EXPLODES = "tntexplodes";

	public function isIntRule() : bool{
		return match($this){
			self::PLAYERS_SLEEPING_PERCENTAGE,
			self::RANDOM_TICK_SPEED,
			self::SPAWN_RADIUS => true,
			default => false
		};
	}

	public function getDefaultValue() : bool|int{
		return match($this){
			self::DO_IMMEDIATE_RESPAWN,
			self::KEEP_INVENTORY,
			self::LOCATOR_BAR,
			self::SHOW_COORDINATES => false,
			self::PLAYERS_SLEEPING_PERCENTAGE => 100,
			self::RANDOM_TICK_SPEED => 1,
			self::SPAWN_RADIUS => 5,
			default => true
		};
	}
}
