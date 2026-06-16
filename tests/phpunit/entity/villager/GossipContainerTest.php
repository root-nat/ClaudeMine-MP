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

namespace pocketmine\entity\villager;

use PHPUnit\Framework\TestCase;

class GossipContainerTest extends TestCase{
	private const PLAYER = "player-uuid";

	public function testTradingRaisesReputation() : void{
		$gossip = new GossipContainer();
		$gossip->add(self::PLAYER, GossipType::TRADING, 10);
		self::assertSame(10, $gossip->getReputation(self::PLAYER));
	}

	public function testMajorNegativeLowersReputation() : void{
		$gossip = new GossipContainer();
		$gossip->add(self::PLAYER, GossipType::MAJOR_NEGATIVE, 10);
		self::assertSame(-50, $gossip->getReputation(self::PLAYER)); //weight -5
	}

	public function testMajorPositiveStronglyRaisesReputation() : void{
		$gossip = new GossipContainer();
		$gossip->add(self::PLAYER, GossipType::MAJOR_POSITIVE, 20);
		self::assertSame(100, $gossip->getReputation(self::PLAYER)); //weight +5
	}

	public function testMixedGossipSums() : void{
		$gossip = new GossipContainer();
		$gossip->add(self::PLAYER, GossipType::TRADING, 25);   //+25
		$gossip->add(self::PLAYER, GossipType::MINOR_NEGATIVE, 10); //-10
		self::assertSame(15, $gossip->getReputation(self::PLAYER));
	}

	public function testValueCappedAtMax() : void{
		$gossip = new GossipContainer();
		$gossip->add(self::PLAYER, GossipType::TRADING, 1000);
		self::assertSame(25, $gossip->getValue(self::PLAYER, GossipType::TRADING)); //trading max 25
	}

	public function testNegativeOrZeroAddIgnored() : void{
		$gossip = new GossipContainer();
		$gossip->add(self::PLAYER, GossipType::TRADING, 0);
		$gossip->add(self::PLAYER, GossipType::TRADING, -5);
		self::assertSame(0, $gossip->getValue(self::PLAYER, GossipType::TRADING));
	}

	public function testDecayReducesValues() : void{
		$gossip = new GossipContainer();
		$gossip->add(self::PLAYER, GossipType::TRADING, 25); //decays 2/day
		$gossip->decay();
		self::assertSame(23, $gossip->getValue(self::PLAYER, GossipType::TRADING));
	}

	public function testDecayRemovesExhaustedGossip() : void{
		$gossip = new GossipContainer();
		$gossip->add(self::PLAYER, GossipType::MINOR_NEGATIVE, 20); //decays 20/day
		$gossip->decay();
		self::assertSame(0, $gossip->getValue(self::PLAYER, GossipType::MINOR_NEGATIVE));
		self::assertEmpty($gossip->getKnownPlayers());
	}

	public function testMajorPositiveNeverDecays() : void{
		$gossip = new GossipContainer();
		$gossip->add(self::PLAYER, GossipType::MAJOR_POSITIVE, 50); //decay 0
		$gossip->decay();
		self::assertSame(50, $gossip->getValue(self::PLAYER, GossipType::MAJOR_POSITIVE));
	}

	public function testSharingSpreadsGossipToAnotherVillager() : void{
		$a = new GossipContainer();
		$b = new GossipContainer();
		$a->add(self::PLAYER, GossipType::MAJOR_NEGATIVE, 100);
		self::assertSame(0, $b->getValue(self::PLAYER, GossipType::MAJOR_NEGATIVE));
		$a->shareWith($b);
		self::assertGreaterThan(0, $b->getValue(self::PLAYER, GossipType::MAJOR_NEGATIVE));
		self::assertLessThanOrEqual(GossipType::MAJOR_NEGATIVE->transferValue(), $b->getValue(self::PLAYER, GossipType::MAJOR_NEGATIVE));
	}

	public function testKnownPlayersTracksAllSubjects() : void{
		$gossip = new GossipContainer();
		$gossip->add("p1", GossipType::TRADING, 5);
		$gossip->add("p2", GossipType::TRADING, 5);
		self::assertCount(2, $gossip->getKnownPlayers());
	}
}
