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

namespace pocketmine\entity\ai\nav;

/**
 * The per-tick movement instruction emitted by {@link PathNavigator}: a normalised horizontal direction, whether to
 * jump this tick, whether the path is complete, and whether the navigator wants a fresh path. Contains no engine state;
 * the entity glue translates this into addMotion()/jump().
 */
final class MoveIntent{

	public function __construct(
		public readonly float $dirX,
		public readonly float $dirZ,
		public readonly bool $jump,
		public readonly bool $arrived,
		public readonly bool $needsRepath
	){}

	public static function idle() : self{
		return new self(0.0, 0.0, false, true, false);
	}

	public static function repath() : self{
		return new self(0.0, 0.0, false, false, true);
	}

	public function hasMovement() : bool{
		return $this->dirX !== 0.0 || $this->dirZ !== 0.0;
	}
}
