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

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use function count;
use function strtolower;

class WeatherCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"weather",
			"Sets the weather",
			"/weather <clear|rain|thunder> [duration in ticks]"
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_WEATHER);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) < 1 || count($args) > 2){
			throw new InvalidCommandSyntaxException();
		}

		$world = $this->getTargetWorld($sender);
		if($world === null){
			$sender->sendMessage(TextFormat::RED . "Unable to find a target world");
			return true;
		}

		$duration = isset($args[1]) ? $this->getInteger($sender, $args[1], 1, 1000000) : null;

		$weather = $world->getWeather();
		switch(strtolower($args[0])){
			case "clear":
				$weather->setClear($duration);
				Command::broadcastCommandMessage($sender, "Changing to clear weather");
				break;
			case "rain":
				$weather->setRain($duration);
				Command::broadcastCommandMessage($sender, "Changing to rainy weather");
				break;
			case "thunder":
				$weather->setThunder($duration);
				Command::broadcastCommandMessage($sender, "Changing to rain and thunder");
				break;
			default:
				throw new InvalidCommandSyntaxException();
		}

		return true;
	}

	private function getTargetWorld(CommandSender $sender) : ?World{
		if($sender instanceof Player){
			return $sender->getWorld();
		}
		return $sender->getServer()->getWorldManager()->getDefaultWorld();
	}
}
