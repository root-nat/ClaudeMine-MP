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
use pocketmine\utils\Utils;
use pocketmine\world\gamerule\GameRule;
use pocketmine\world\World;
use function count;
use function implode;
use function is_bool;
use function is_numeric;
use function strtolower;

class GameRuleCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"gamerule",
			"Sets or queries a game rule value",
			"/gamerule [rule] [value]"
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_GAMERULE);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) > 2){
			throw new InvalidCommandSyntaxException();
		}

		$world = $this->getTargetWorld($sender);
		if($world === null){
			$sender->sendMessage(TextFormat::RED . "Unable to find a target world");
			return true;
		}

		if(count($args) === 0){
			$lines = [];
			foreach(Utils::stringifyKeys($world->getGameRules()->getAll()) as $name => $value){
				$lines[] = $name . " = " . self::formatValue($value);
			}
			$sender->sendMessage(implode(", ", $lines));
			return true;
		}

		$rule = GameRule::tryFrom(strtolower($args[0]));
		if($rule === null){
			$sender->sendMessage(TextFormat::RED . "Unknown game rule \"$args[0]\"");
			return true;
		}

		if(count($args) === 1){
			$sender->sendMessage($rule->value . " = " . self::formatValue($world->getGameRules()->get($rule)));
			return true;
		}

		if($rule->isIntRule()){
			if(!is_numeric($args[1])){
				$sender->sendMessage(TextFormat::RED . "Game rule \"{$rule->value}\" expects an integer value");
				return true;
			}
			$value = (int) $args[1];
		}else{
			$value = match(strtolower($args[1])){
				"true" => true,
				"false" => false,
				default => null
			};
			if($value === null){
				$sender->sendMessage(TextFormat::RED . "Game rule \"{$rule->value}\" expects \"true\" or \"false\"");
				return true;
			}
		}

		$world->setGameRule($rule, $value);
		Command::broadcastCommandMessage($sender, "Game rule {$rule->value} has been updated to " . self::formatValue($value));

		return true;
	}

	private static function formatValue(bool|int $value) : string{
		return is_bool($value) ? ($value ? "true" : "false") : (string) $value;
	}

	private function getTargetWorld(CommandSender $sender) : ?World{
		if($sender instanceof Player){
			return $sender->getWorld();
		}
		return $sender->getServer()->getWorldManager()->getDefaultWorld();
	}
}
