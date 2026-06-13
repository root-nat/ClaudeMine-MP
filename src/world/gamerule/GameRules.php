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

use pocketmine\utils\Utils;
use function is_bool;
use function is_int;

final class GameRules{

	/**
	 * @var bool[]|int[]
	 * @phpstan-var array<string, bool|int>
	 */
	private array $values = [];

	/**
	 * @param bool[]|int[] $values
	 * @phpstan-param array<string, bool|int> $values
	 */
	public function __construct(array $values = []){
		foreach(Utils::stringifyKeys($values) as $name => $value){
			$rule = GameRule::tryFrom($name);
			if($rule === null){
				continue;
			}
			if($rule->isIntRule() === is_int($value) || (!$rule->isIntRule() && is_bool($value))){
				$this->values[$rule->value] = $value;
			}
		}
	}

	public function get(GameRule $rule) : bool|int{
		return $this->values[$rule->value] ?? $rule->getDefaultValue();
	}

	public function getBool(GameRule $rule) : bool{
		if($rule->isIntRule()){
			throw new \InvalidArgumentException("Game rule \"{$rule->value}\" is an integer rule, not a boolean rule");
		}
		$value = $this->get($rule);
		return is_bool($value) ? $value : $value !== 0;
	}

	public function getInt(GameRule $rule) : int{
		if(!$rule->isIntRule()){
			throw new \InvalidArgumentException("Game rule \"{$rule->value}\" is a boolean rule, not an integer rule");
		}
		$value = $this->get($rule);
		return is_int($value) ? $value : ($value ? 1 : 0);
	}

	public function set(GameRule $rule, bool|int $value) : void{
		if($rule->isIntRule() !== is_int($value)){
			throw new \InvalidArgumentException("Game rule \"{$rule->value}\" expects a " . ($rule->isIntRule() ? "integer" : "boolean") . " value");
		}
		$this->values[$rule->value] = $value;
	}

	public function reset(GameRule $rule) : void{
		unset($this->values[$rule->value]);
	}

	/**
	 * Returns the resolved value of every known game rule.
	 *
	 * @return bool[]|int[]
	 * @phpstan-return array<string, bool|int>
	 */
	public function getAll() : array{
		$result = [];
		foreach(GameRule::cases() as $rule){
			$result[$rule->value] = $this->get($rule);
		}
		return $result;
	}
}
