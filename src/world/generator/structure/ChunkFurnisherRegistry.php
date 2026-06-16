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

namespace pocketmine\world\generator\structure;

use pocketmine\utils\SingletonTrait;

/**
 * Maps a generator name to the {@link ChunkFurnisher}s that finish its structures on the main thread after population.
 * The World consults this once per populated chunk. Built-in furnishers register themselves here, mirroring how the
 * {@link \pocketmine\world\generator\GeneratorManager} registers built-in generators.
 */
final class ChunkFurnisherRegistry{
	use SingletonTrait;

	/**
	 * @var ChunkFurnisher[][]
	 * @phpstan-var array<string, list<ChunkFurnisher>>
	 */
	private array $furnishers = [];

	public function __construct(){
		//register under both names the Nether generator is known by (GeneratorManager aliases "nether" -> "hell"), so a
		//world created with either name still gets its bastions furnished
		$bastionFurnisher = new NetherBastionFurnisher();
		$this->register("nether", $bastionFurnisher);
		$this->register("hell", $bastionFurnisher);
	}

	public function register(string $generatorName, ChunkFurnisher $furnisher) : void{
		$this->furnishers[$generatorName][] = $furnisher;
	}

	/**
	 * @return ChunkFurnisher[]
	 * @phpstan-return list<ChunkFurnisher>
	 */
	public function getFurnishers(string $generatorName) : array{
		return $this->furnishers[$generatorName] ?? [];
	}
}
