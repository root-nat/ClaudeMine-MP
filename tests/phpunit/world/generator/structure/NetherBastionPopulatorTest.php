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

namespace pocketmine\world\generator\structure;

use PHPUnit\Framework\TestCase;
use pocketmine\utils\Random;
use function explode;

class NetherBastionPopulatorTest extends TestCase{

	/**
	 * @return string[] every bastion anchor found across a chunk area, as "ax:ay:az"
	 */
	private function collectAnchors(int $worldSeed) : array{
		$anchors = [];
		for($cx = 0; $cx < 48; ++$cx){
			for($cz = 0; $cz < 48; ++$cz){
				NetherBastionPopulator::forEachOrigin($worldSeed, $cx, $cz, function(int $ax, int $ay, int $az, Random $random) use (&$anchors) : void{
					$anchors[] = "$ax:$ay:$az";
				});
			}
		}
		return $anchors;
	}

	public function testForEachOriginIsDeterministic() : void{
		//the furnisher (main thread) relies on this producing the EXACT same anchors as the async populator
		self::assertSame($this->collectAnchors(778899), $this->collectAnchors(778899));
	}

	public function testForEachOriginFindsBastions() : void{
		//a 48x48 chunk area should contain several bastions at rarity 300
		self::assertNotEmpty($this->collectAnchors(778899));
	}

	public function testAnchorsAreWithinDeckRange() : void{
		foreach($this->collectAnchors(424242) as $anchor){
			$ay = (int) explode(":", $anchor)[1];
			self::assertGreaterThanOrEqual(NetherBastionPopulator::MIN_DECK_Y, $ay);
			self::assertLessThanOrEqual(NetherBastionPopulator::MAX_DECK_Y, $ay);
		}
	}
}
