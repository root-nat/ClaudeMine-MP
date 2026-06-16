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

namespace pocketmine\entity\projectile;

use PHPUnit\Framework\TestCase;
use pocketmine\item\VanillaItems;

class FishingLootTest extends TestCase{

	public function testFishIsTheCommonCatch() : void{
		self::assertSame(VanillaItems::RAW_FISH()->getTypeId(), FishingLoot::roll(0.0, 0.0)->getTypeId());
		self::assertSame(VanillaItems::RAW_SALMON()->getTypeId(), FishingLoot::roll(0.5, 0.7)->getTypeId());
		self::assertSame(VanillaItems::PUFFERFISH()->getTypeId(), FishingLoot::roll(0.84, 0.9)->getTypeId());
	}

	public function testJunkBand() : void{
		//category roll in [0.85, 0.95)
		self::assertSame(VanillaItems::STICK()->getTypeId(), FishingLoot::roll(0.9, 0.0)->getTypeId());
		self::assertSame(VanillaItems::ROTTEN_FLESH()->getTypeId(), FishingLoot::roll(0.9, 0.75)->getTypeId());
	}

	public function testTreasureBand() : void{
		//category roll >= 0.95
		self::assertSame(VanillaItems::NAME_TAG()->getTypeId(), FishingLoot::roll(0.97, 0.0)->getTypeId());
		self::assertSame(VanillaItems::LEATHER_BOOTS()->getTypeId(), FishingLoot::roll(0.99, 0.9)->getTypeId());
	}
}
