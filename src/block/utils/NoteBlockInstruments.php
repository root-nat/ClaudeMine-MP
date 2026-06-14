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

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Glass;
use pocketmine\block\GlassPane;
use pocketmine\block\Gravel;
use pocketmine\block\Planks;
use pocketmine\block\Sand;
use pocketmine\block\Wood;
use pocketmine\block\Wool;
use pocketmine\world\sound\NoteInstrument;

/**
 * Pure mapping from the block beneath a note block to the instrument it plays, isolated from the live World so it can be
 * unit-tested. Mirrors the vanilla note-block instrument table; any block not listed plays the default harp (piano).
 */
final class NoteBlockInstruments{

	private function __construct(){
		//NOOP
	}

	public static function fromBlockBelow(Block $below) : NoteInstrument{
		$byId = match($below->getTypeId()){
			BlockTypeIds::GOLD => NoteInstrument::BELL,
			BlockTypeIds::CLAY => NoteInstrument::FLUTE,
			BlockTypeIds::PACKED_ICE => NoteInstrument::CHIME,
			BlockTypeIds::BONE_BLOCK => NoteInstrument::XYLOPHONE,
			BlockTypeIds::IRON => NoteInstrument::IRON_XYLOPHONE,
			BlockTypeIds::SOUL_SAND => NoteInstrument::COW_BELL,
			BlockTypeIds::PUMPKIN, BlockTypeIds::CARVED_PUMPKIN => NoteInstrument::DIDGERIDOO,
			BlockTypeIds::EMERALD => NoteInstrument::BIT,
			BlockTypeIds::HAY_BALE => NoteInstrument::BANJO,
			BlockTypeIds::GLOWSTONE => NoteInstrument::PLING,
			BlockTypeIds::STONE, BlockTypeIds::COBBLESTONE, BlockTypeIds::MOSSY_COBBLESTONE,
			BlockTypeIds::SMOOTH_STONE, BlockTypeIds::STONE_BRICKS, BlockTypeIds::MOSSY_STONE_BRICKS,
			BlockTypeIds::ANDESITE, BlockTypeIds::DIORITE, BlockTypeIds::GRANITE,
			BlockTypeIds::NETHERRACK, BlockTypeIds::BLACKSTONE,
			BlockTypeIds::DEEPSLATE, BlockTypeIds::COBBLED_DEEPSLATE => NoteInstrument::BASS_DRUM,
			default => null
		};
		if($byId !== null){
			return $byId;
		}

		return match(true){
			$below instanceof Wool => NoteInstrument::GUITAR,
			$below instanceof Planks, $below instanceof Wood => NoteInstrument::DOUBLE_BASS,
			$below instanceof Sand, $below instanceof Gravel => NoteInstrument::SNARE,
			$below instanceof Glass, $below instanceof GlassPane => NoteInstrument::CLICKS_AND_STICKS,
			default => NoteInstrument::PIANO
		};
	}
}
