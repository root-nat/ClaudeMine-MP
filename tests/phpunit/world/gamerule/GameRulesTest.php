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

use PHPUnit\Framework\TestCase;
use function count;

class GameRulesTest extends TestCase{

	public function testDefaultsAreReturned() : void{
		$rules = new GameRules();
		self::assertTrue($rules->getBool(GameRule::DO_DAYLIGHT_CYCLE));
		self::assertFalse($rules->getBool(GameRule::KEEP_INVENTORY));
		self::assertSame(1, $rules->getInt(GameRule::RANDOM_TICK_SPEED));
	}

	public function testSetAndGet() : void{
		$rules = new GameRules();
		$rules->set(GameRule::KEEP_INVENTORY, true);
		$rules->set(GameRule::RANDOM_TICK_SPEED, 3);
		self::assertTrue($rules->getBool(GameRule::KEEP_INVENTORY));
		self::assertSame(3, $rules->getInt(GameRule::RANDOM_TICK_SPEED));
	}

	public function testReset() : void{
		$rules = new GameRules();
		$rules->set(GameRule::DO_FIRE_TICK, false);
		self::assertFalse($rules->getBool(GameRule::DO_FIRE_TICK));
		$rules->reset(GameRule::DO_FIRE_TICK);
		self::assertTrue($rules->getBool(GameRule::DO_FIRE_TICK));
	}

	public function testSetWrongTypeThrows() : void{
		$rules = new GameRules();
		$this->expectException(\InvalidArgumentException::class);
		$rules->set(GameRule::KEEP_INVENTORY, 1);
	}

	public function testGetBoolOnIntRuleThrows() : void{
		$rules = new GameRules();
		$this->expectException(\InvalidArgumentException::class);
		$rules->getBool(GameRule::RANDOM_TICK_SPEED);
	}

	public function testGetIntOnBoolRuleThrows() : void{
		$rules = new GameRules();
		$this->expectException(\InvalidArgumentException::class);
		$rules->getInt(GameRule::PVP);
	}

	public function testGetAllCoversEveryRule() : void{
		$rules = new GameRules();
		$all = $rules->getAll();
		self::assertSame(count(GameRule::cases()), count($all));
		foreach(GameRule::cases() as $rule){
			self::assertArrayHasKey($rule->value, $all);
		}
	}

	public function testConstructorFiltersUnknownAndMistypedValues() : void{
		$rules = new GameRules([
			"dodaylightcycle" => false,
			"randomtickspeed" => true,
			"nonexistentrule" => true
		]);
		self::assertFalse($rules->getBool(GameRule::DO_DAYLIGHT_CYCLE));
		self::assertSame(1, $rules->getInt(GameRule::RANDOM_TICK_SPEED));
	}
}
