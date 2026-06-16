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

namespace pocketmine\world\weather;

use PHPUnit\Framework\TestCase;

class WeatherManagerTest extends TestCase{

	public function testRainDurationBounds() : void{
		for($i = 0; $i < 100; ++$i){
			$duration = WeatherManager::pickRainDuration();
			self::assertGreaterThanOrEqual(12000, $duration);
			self::assertLessThan(24000, $duration);
		}
	}

	public function testClearRainDurationBounds() : void{
		for($i = 0; $i < 100; ++$i){
			$duration = WeatherManager::pickClearRainDuration();
			self::assertGreaterThanOrEqual(12000, $duration);
			self::assertLessThan(180000, $duration);
		}
	}

	public function testThunderDurationBounds() : void{
		for($i = 0; $i < 100; ++$i){
			$duration = WeatherManager::pickThunderDuration();
			self::assertGreaterThanOrEqual(3600, $duration);
			self::assertLessThan(15600, $duration);
		}
	}

	public function testClearThunderDurationBounds() : void{
		for($i = 0; $i < 100; ++$i){
			$duration = WeatherManager::pickClearThunderDuration();
			self::assertGreaterThanOrEqual(12000, $duration);
			self::assertLessThan(180000, $duration);
		}
	}
}
