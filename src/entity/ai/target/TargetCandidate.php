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

namespace pocketmine\entity\ai\target;

use pocketmine\math\Vector3;

/**
 * An immutable snapshot of a potential target produced by a sensor from a live entity. The target selector and goals
 * operate purely on these value objects, never on live entities, keeping their logic unit-testable.
 */
final class TargetCandidate{

	public function __construct(
		public readonly int $entityId,
		public readonly float $x,
		public readonly float $y,
		public readonly float $z,
		public readonly bool $alive,
		public readonly bool $isPlayer
	){}

	public function position() : Vector3{
		return new Vector3($this->x, $this->y, $this->z);
	}

	public function distanceSquaredTo(float $x, float $y, float $z) : float{
		$dx = $this->x - $x;
		$dy = $this->y - $y;
		$dz = $this->z - $z;
		return ($dx * $dx) + ($dy * $dy) + ($dz * $dz);
	}
}
