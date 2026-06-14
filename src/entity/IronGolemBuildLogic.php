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

namespace pocketmine\entity;

/**
 * Pure recogniser for the iron golem frame, so it can be unit-tested without a world. A pumpkin completes a golem when an
 * iron block sits directly below it (the body), another iron block below that (the base/legs), and a matching pair of
 * iron arms sticks straight out from the body along one horizontal axis. Returns which axis the arms lie on so the caller
 * knows which two arm blocks to consume, or null when the frame is incomplete.
 */
final class IronGolemBuildLogic{

	public const AXIS_X = "x";
	public const AXIS_Z = "z";

	public static function matchedAxis(bool $bodyIron, bool $baseIron, bool $westIron, bool $eastIron, bool $northIron, bool $southIron) : ?string{
		if(!$bodyIron || !$baseIron){
			return null;
		}
		if($westIron && $eastIron){
			return self::AXIS_X;
		}
		if($northIron && $southIron){
			return self::AXIS_Z;
		}
		return null;
	}
}
