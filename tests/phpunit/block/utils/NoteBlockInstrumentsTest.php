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

namespace pocketmine\block\utils;

use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\sound\NoteInstrument;

class NoteBlockInstrumentsTest extends TestCase{

	public function testSpecificBlocksMapToInstruments() : void{
		self::assertSame(NoteInstrument::BELL, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::GOLD()));
		self::assertSame(NoteInstrument::FLUTE, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::CLAY()));
		self::assertSame(NoteInstrument::CHIME, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::PACKED_ICE()));
		self::assertSame(NoteInstrument::XYLOPHONE, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::BONE_BLOCK()));
		self::assertSame(NoteInstrument::IRON_XYLOPHONE, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::IRON()));
		self::assertSame(NoteInstrument::COW_BELL, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::SOUL_SAND()));
		self::assertSame(NoteInstrument::DIDGERIDOO, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::PUMPKIN()));
		self::assertSame(NoteInstrument::DIDGERIDOO, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::CARVED_PUMPKIN()));
		self::assertSame(NoteInstrument::BIT, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::EMERALD()));
		self::assertSame(NoteInstrument::BANJO, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::HAY_BALE()));
		self::assertSame(NoteInstrument::PLING, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::GLOWSTONE()));
	}

	public function testMaterialFamiliesMapToInstruments() : void{
		self::assertSame(NoteInstrument::DOUBLE_BASS, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::OAK_PLANKS()));
		self::assertSame(NoteInstrument::DOUBLE_BASS, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::OAK_LOG()));
		self::assertSame(NoteInstrument::SNARE, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::SAND()));
		self::assertSame(NoteInstrument::SNARE, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::GRAVEL()));
		self::assertSame(NoteInstrument::GUITAR, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::WOOL()));
		self::assertSame(NoteInstrument::CLICKS_AND_STICKS, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::GLASS()));
		self::assertSame(NoteInstrument::BASS_DRUM, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::STONE()));
		self::assertSame(NoteInstrument::BASS_DRUM, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::SMOOTH_STONE()));
		self::assertSame(NoteInstrument::BASS_DRUM, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::DEEPSLATE()));
	}

	public function testUnknownBlockDefaultsToPiano() : void{
		self::assertSame(NoteInstrument::PIANO, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::DIRT()));
		self::assertSame(NoteInstrument::PIANO, NoteBlockInstruments::fromBlockBelow(VanillaBlocks::AIR()));
	}
}
