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

/**
 * A small jigsaw-assembled village (well plaza + paths + houses, see {@link VillageTemplates}), themed by a {@link
 * VillagePalette} so plains/savanna/taiga get oak, the desert gets sandstone and the snowy taiga gets spruce. The
 * SALT/RARITY/MAX_RADIUS/SURFACE_* constants are shared verbatim by the {@link SurfaceStructurePopulator}s that place it
 * (one per biome theme, all on the same salt so they share anchors), the {@link VillageFurnisher} that fills its chests
 * and spawns its villagers, and the /locate command - so all of them agree on where villages are.
 *
 * The palette only changes block ids, never geometry, so a single instance (any palette) re-derives the same piece and
 * marker layout - which is why the furnisher can use {@link VillagePalette::plains()} regardless of a village's theme.
 *
 * MAX_RADIUS is 15 on purpose: surface re-derivation needs the anchor chunk to be loaded when each touched chunk is
 * populated, which is guaranteed only within the anchor chunk's 3x3 population neighbourhood. A 15-block half-extent
 * keeps the whole village inside that neighbourhood, so every chunk re-derives the same ground Y and writes its slice
 * with no persisted structure-start cache. (Sprawling full-size villages would need such a cache; that is a later step.)
 */
final class VillageStructure extends JigsawStructure{

	public const SALT = 0x76696C6C; //"vill"
	public const RARITY = 48;
	public const MAX_RADIUS = 15;
	public const SURFACE_TOP_Y = 120;
	public const SURFACE_MIN_Y = 48;

	private const MAX_PIECES = 9;
	private const HALF_EXTENT = 15;
	private const MAX_HEIGHT = 8;
	private const FOUNDATION_DEPTH = 4;

	public function __construct(VillagePalette $palette){
		parent::__construct(
			VillageTemplates::start($palette),
			VillageTemplates::pools($palette),
			self::MAX_PIECES,
			self::HALF_EXTENT,
			self::MAX_HEIGHT,
			self::FOUNDATION_DEPTH,
			$palette->foundation
		);
	}
}
