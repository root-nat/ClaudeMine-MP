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

namespace pocketmine\entity\ai;

/**
 * Pure decision for which enemy a wolf should fight, mirroring vanilla's two target goals: a wolf retaliates against
 * whatever recently hurt it ("owner hurt by"), otherwise a tamed wolf strikes back at whatever recently hurt its owner
 * ("hurt the owner"). It never turns on its own owner, and a sitting wolf stays put and never engages. Both candidates
 * are pre-filtered by the sensor (resolved to a live, in-range entity) and reported as "engageable"; this stays free of
 * any entity/world access so it is exhaustively unit-testable.
 *
 * @return int|null the entity id to fight, or null to stand down
 */
final class WolfCombatLogic{

	public static function chooseTargetId(bool $sitting, ?int $ownerId, ?int $hurtById, bool $hurtByEngageable, ?int $ownerAttackerId, bool $ownerAttackerEngageable) : ?int{
		if($sitting){
			return null;
		}
		if($hurtById !== null && $hurtById !== $ownerId && $hurtByEngageable){
			return $hurtById;
		}
		if($ownerAttackerId !== null && $ownerAttackerId !== $ownerId && $ownerAttackerEngageable){
			return $ownerAttackerId;
		}
		return null;
	}
}
