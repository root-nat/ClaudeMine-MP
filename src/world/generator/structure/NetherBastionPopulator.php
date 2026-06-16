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

use Closure;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\carver\ChunkManagerVolume;
use pocketmine\world\generator\carver\RegionRandom;
use pocketmine\world\generator\populator\Populator;
use function intdiv;

/**
 * Places nether bastions (block states) during async population. The placement decision is shared with the main-thread
 * {@link NetherBastionFurnisher} through {@link self::forEachOrigin}: both walk the same region grid with the same
 * RegionRandom and the same constants, so the furnisher's loot chest and piglins land exactly where this populator put
 * their blocks. A bastion is anchored at a fixed deck height over the lava seas (no cave-floor search).
 */
final class NetherBastionPopulator implements Populator{

	public const SALT = 0x2b71d;
	public const RARITY = 300;
	public const MIN_DECK_Y = 50;
	public const MAX_DECK_Y = 78;

	public function __construct(
		private int $worldSeed,
		private NetherBastionStructure $bastion
	){}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$volume = new ChunkManagerVolume($world);
		self::forEachOrigin($this->worldSeed, $chunkX, $chunkZ, function(int $ax, int $ay, int $az, Random $regionRandom) use ($volume) : void{
			$this->bastion->place($volume, $ax, $ay, $az, $regionRandom);
		});
	}

	/**
	 * The chunk scan radius (in chunks) that covers a bastion footprint: a bastion anchored anywhere in its origin chunk
	 * reaches MAX_RADIUS blocks out, i.e. up to this many chunks away.
	 */
	public static function scanRadius() : int{
		return intdiv(NetherBastionStructure::MAX_RADIUS + Chunk::EDGE_LENGTH - 1, Chunk::EDGE_LENGTH);
	}

	/**
	 * Invokes $callback once per bastion whose footprint can reach the given chunk, with its anchor (ax, ay, az) and the
	 * region Random left exactly as the structure's place() would receive it. Deterministic for a world seed; SHARED by
	 * the async populator and the main-thread furnisher so both agree on every bastion's position.
	 *
	 * @phpstan-param Closure(int, int, int, Random) : void $callback
	 */
	public static function forEachOrigin(int $worldSeed, int $chunkX, int $chunkZ, Closure $callback) : void{
		$scanRadius = self::scanRadius();
		for($rx = $chunkX - $scanRadius; $rx <= $chunkX + $scanRadius; ++$rx){
			for($rz = $chunkZ - $scanRadius; $rz <= $chunkZ + $scanRadius; ++$rz){
				$random = RegionRandom::derive($worldSeed, $rx, $rz, self::SALT);
				if(self::RARITY > 1 && $random->nextBoundedInt(self::RARITY) !== 0){
					continue;
				}
				$ax = $rx * Chunk::EDGE_LENGTH + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
				$az = $rz * Chunk::EDGE_LENGTH + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
				$ay = self::MIN_DECK_Y + $random->nextBoundedInt(self::MAX_DECK_Y - self::MIN_DECK_Y + 1);
				$callback($ax, $ay, $az, $random);
			}
		}
	}
}
