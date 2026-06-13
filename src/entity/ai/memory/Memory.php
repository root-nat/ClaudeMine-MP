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

use pocketmine\utils\Utils;

/**
 * A mob's working memory: a typed key/value store with optional tick-based expiry, shared between sensors, the target
 * selector and goals. Pure; expiry is advanced explicitly via {@link Memory::tickExpiries()}.
 */
final class Memory{

	/** @var array<string, mixed> */
	private array $values = [];
	/** @var array<string, int> remaining ticks before the entry expires, keyed by module value */
	private array $expiry = [];

	public function set(MemoryModuleType $type, mixed $value, ?int $ttlTicks = null) : void{
		$this->values[$type->value] = $value;
		if($ttlTicks !== null){
			$this->expiry[$type->value] = $ttlTicks;
		}else{
			unset($this->expiry[$type->value]);
		}
	}

	public function has(MemoryModuleType $type) : bool{
		return array_key_exists($type->value, $this->values);
	}

	public function get(MemoryModuleType $type) : mixed{
		return $this->values[$type->value] ?? null;
	}

	public function erase(MemoryModuleType $type) : void{
		unset($this->values[$type->value], $this->expiry[$type->value]);
	}

	/**
	 * Decrements all TTLs by the given tick count and erases any that have expired.
	 */
	public function tickExpiries(int $tickDiff = 1) : void{
		foreach(Utils::stringifyKeys($this->expiry) as $key => $remaining){
			$remaining -= $tickDiff;
			if($remaining <= 0){
				unset($this->values[$key], $this->expiry[$key]);
			}else{
				$this->expiry[$key] = $remaining;
			}
		}
	}
}
