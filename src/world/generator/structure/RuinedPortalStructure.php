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

use pocketmine\utils\Random;
use pocketmine\world\generator\carver\GenerationVolume;
use function abs;

/**
 * A ruined portal: a broken obsidian portal frame (speckled with crying obsidian and gaps) standing on a netherrack rubble
 * base with magma, gold and stone-brick remains, and a loot chest (filled by {@link OverworldStructureFurnisher}).
 *
 * Built OFFSET from the anchor so the anchor column (0,0) stays open ground (stable surface scan). Geometry is fully
 * fixed - the "ruin" (which frame blocks decay to crying obsidian or to a gap) is a fixed positional rule, not Random.
 * No flowing lava is placed (magma blocks stand in) so nothing spreads across a chunk seam.
 */
final class RuinedPortalStructure extends Structure{

	public const MAX_RADIUS = 9;
	public const SALT = 0x5a170a;
	public const RARITY = 26;

	private const CENTER = 4; //portal centre X offset from the anchor; (0,0) stays open ground

	/** @var array{int, int, int} anchor-relative loot chest position (on the rubble base) */
	public const CHEST_OFFSET = [self::CENTER + 1, 0, 2];

	private int $originX = 0;
	private int $originY = 0;
	private int $originZ = 0;

	public function __construct(
		private int $obsidianStateId,
		private int $cryingObsidianStateId,
		private int $netherrackStateId,
		private int $goldStateId,
		private int $magmaStateId,
		private int $stoneBricksStateId,
		private int $chestStateId
	){}

	public function canPlace(GenerationVolume $volume, int $x, int $y, int $z) : bool{
		return ($y - 2) > $volume->getMinY() && ($y + 6) < $volume->getMaxY();
	}

	public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		$this->originX = $x;
		$this->originY = $y;
		$this->originZ = $z;

		$c = self::CENTER;
		//rubble base just under the portal: netherrack speckled with magma and the odd gold block
		for($a = -1; $a <= 4; ++$a){
			for($b = -1; $b <= 2; ++$b){
				$state = match(true){
					(($a * 3 + $b) & 7) === 0 => $this->goldStateId,
					(($a + $b * 2) & 3) === 0 => $this->magmaStateId,
					default => $this->netherrackStateId
				};
				$this->set($volume, $c + $a, -1, $b, $state);
			}
		}
		//broken obsidian frame standing in the b=0 plane (interior a=1..2, dy=1..3 is the inert opening)
		for($a = 0; $a <= 3; ++$a){
			for($dy = 0; $dy <= 4; ++$dy){
				$isFrame = $dy === 0 || $dy === 4 || $a === 0 || $a === 3;
				if(!$isFrame){
					continue; //the portal opening
				}
				if((($a + $dy) % 7) === 0 && !($a === 0 && $dy === 0)){
					continue; //a missing block - the ruined look (keep one foot anchored)
				}
				$state = ((($a * 3 + $dy) % 4) === 0) ? $this->cryingObsidianStateId : $this->obsidianStateId;
				$this->set($volume, $c + $a, $dy, 0, $state);
			}
		}
		//scattered stone-brick remains around the base
		$this->set($volume, $c - 1, 0, -1, $this->stoneBricksStateId);
		$this->set($volume, $c + 4, 0, 2, $this->stoneBricksStateId);
		$this->set($volume, $c + 2, 0, 2, $this->stoneBricksStateId);
		//the loot chest on the rubble
		[$cdx, $cdy, $cdz] = self::CHEST_OFFSET;
		$this->set($volume, $cdx, $cdy, $cdz, $this->chestStateId);
	}

	private function set(GenerationVolume $volume, int $dx, int $dy, int $dz, int $stateId) : void{
		if(abs($dx) > self::MAX_RADIUS || abs($dz) > self::MAX_RADIUS){
			return;
		}
		$x = $this->originX + $dx;
		$y = $this->originY + $dy;
		$z = $this->originZ + $dz;
		if($volume->isInBounds($x, $y, $z)){
			$volume->setBlockStateId($x, $y, $z, $stateId);
		}
	}
}
