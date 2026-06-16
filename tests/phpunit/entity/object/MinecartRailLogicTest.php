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

namespace pocketmine\entity\object;

use PHPUnit\Framework\TestCase;
use pocketmine\data\bedrock\block\BlockLegacyMetadata as Meta;
use pocketmine\math\Facing;

class MinecartRailLogicTest extends TestCase{

	public function testStraightNorthSouthConnections() : void{
		$connections = MinecartRailLogic::getConnectionsForShape(Meta::RAIL_STRAIGHT_NORTH_SOUTH);
		self::assertNotNull($connections);
		self::assertContains(Facing::NORTH, $connections);
		self::assertContains(Facing::SOUTH, $connections);
	}

	public function testStraightRailPreservesDirection() : void{
		$connections = MinecartRailLogic::getConnectionsForShape(Meta::RAIL_STRAIGHT_NORTH_SOUTH);
		self::assertSame(Facing::SOUTH, MinecartRailLogic::getNextDirection($connections, Facing::SOUTH));
		self::assertSame(Facing::NORTH, MinecartRailLogic::getNextDirection($connections, Facing::NORTH));
	}

	public function testCurveTurnsCart() : void{
		//south-east curve: a cart moving NORTH (entered from the south) must turn EAST
		$connections = MinecartRailLogic::getConnectionsForShape(Meta::RAIL_CURVE_SOUTHEAST);
		self::assertNotNull($connections);
		self::assertSame(Facing::EAST, MinecartRailLogic::getNextDirection($connections, Facing::NORTH));
	}

	public function testCurveTurnsCartFromOtherAxis() : void{
		//south-east curve: a cart moving WEST (entered from the east) must turn SOUTH
		$connections = MinecartRailLogic::getConnectionsForShape(Meta::RAIL_CURVE_SOUTHEAST);
		self::assertSame(Facing::SOUTH, MinecartRailLogic::getNextDirection($connections, Facing::WEST));
	}

	public function testAscendingDetection() : void{
		self::assertTrue(MinecartRailLogic::isAscending(Meta::RAIL_ASCENDING_EAST));
		self::assertFalse(MinecartRailLogic::isAscending(Meta::RAIL_STRAIGHT_NORTH_SOUTH));
	}

	public function testAscendingConnectionsStripFlag() : void{
		$connections = MinecartRailLogic::getConnectionsForShape(Meta::RAIL_ASCENDING_EAST);
		self::assertNotNull($connections);
		self::assertContains(Facing::WEST, $connections);
		self::assertContains(Facing::EAST, $connections);
	}

	public function testCanTravel() : void{
		$connections = MinecartRailLogic::getConnectionsForShape(Meta::RAIL_STRAIGHT_EAST_WEST);
		self::assertTrue(MinecartRailLogic::canTravel($connections, Facing::EAST));
		self::assertTrue(MinecartRailLogic::canTravel($connections, Facing::WEST));
		self::assertFalse(MinecartRailLogic::canTravel($connections, Facing::NORTH));
	}

	public function testUnknownShapeReturnsNull() : void{
		self::assertNull(MinecartRailLogic::getConnectionsForShape(9999));
	}
}
