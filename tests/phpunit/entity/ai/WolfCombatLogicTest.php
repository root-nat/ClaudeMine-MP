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

use PHPUnit\Framework\TestCase;

class WolfCombatLogicTest extends TestCase{

	public function testSittingWolfNeverFights() : void{
		//both a personal attacker and an owner-attacker present, but sitting overrides everything
		self::assertNull(WolfCombatLogic::chooseTargetId(true, 1, 7, true, 9, true));
	}

	public function testRetaliatesAgainstItsAttacker() : void{
		self::assertSame(7, WolfCombatLogic::chooseTargetId(false, 1, 7, true, null, false));
	}

	public function testNeverRetaliatesAgainstItsOwner() : void{
		//owner (id 1) hit the wolf by accident: ignore it, and with nothing else to fight, stand down
		self::assertNull(WolfCombatLogic::chooseTargetId(false, 1, 1, true, null, false));
	}

	public function testIgnoresAnUnreachableAttacker() : void{
		//attacker is out of range / dead (not engageable): don't lock onto a stale snapshot
		self::assertNull(WolfCombatLogic::chooseTargetId(false, 1, 7, false, null, false));
	}

	public function testDefendsOwnerAgainstItsAttacker() : void{
		self::assertSame(20, WolfCombatLogic::chooseTargetId(false, 1, null, false, 20, true));
	}

	public function testNeverDefendsByTargetingTheOwner() : void{
		//pathological: the owner's "attacker" resolves to the owner id — never target the owner
		self::assertNull(WolfCombatLogic::chooseTargetId(false, 1, null, false, 1, true));
	}

	public function testIgnoresAnUnreachableOwnerAttacker() : void{
		self::assertNull(WolfCombatLogic::chooseTargetId(false, 1, null, false, 20, false));
	}

	public function testRetaliationOutranksDefendingTheOwner() : void{
		//being personally attacked takes priority over avenging the owner
		self::assertSame(7, WolfCombatLogic::chooseTargetId(false, 1, 7, true, 20, true));
	}

	public function testFallsBackToOwnerDefenceWhenAttackerIsOwner() : void{
		//hurt by the owner (ignored) but a creeper just hit the owner: avenge them instead
		self::assertSame(20, WolfCombatLogic::chooseTargetId(false, 1, 1, true, 20, true));
	}

	public function testStandsDownWithNoEnemies() : void{
		self::assertNull(WolfCombatLogic::chooseTargetId(false, 1, null, false, null, false));
	}

	public function testWildWolfWithNoOwnerStillDefendsItself() : void{
		self::assertSame(7, WolfCombatLogic::chooseTargetId(false, null, 7, true, null, false));
	}
}
