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
 * A swamp hut (witch hut): a small spruce shack raised on log stilts, with a cauldron, crafting table and a barred-ish
 * doorway. A witch is spawned on its deck by {@link OverworldStructureFurnisher} (see {@link self::WITCH_OFFSET}).
 *
 * The hut is built OFFSET to one side of the anchor, so the anchor column (0,0) stays open swamp ground - the surface
 * scan there returns the same groundY whether or not the hut is built, keeping placement seamless across chunks and the
 * witch spawn point exact. Geometry is fully fixed (no Random).
 */
final class WitchHutStructure extends Structure{

	public const MAX_RADIUS = 9;
	public const SALT = 0x5a1703;
	public const RARITY = 28;

	/** Forward (X) offset of the hut centre from the anchor, so the anchor column itself is left as open ground. */
	private const CENTER = 5;
	private const DECK_Y = 2;

	/** @var array{int, int, int} anchor-relative spot where the furnisher stands the witch (on the deck) */
	public const WITCH_OFFSET = [self::CENTER, self::DECK_Y + 1, 0];

	private int $originX = 0;
	private int $originY = 0;
	private int $originZ = 0;

	public function __construct(
		private int $airStateId,
		private int $planksStateId,
		private int $logStateId,
		private int $fenceStateId,
		private int $cauldronStateId,
		private int $craftingTableStateId
	){}

	public function canPlace(GenerationVolume $volume, int $x, int $y, int $z) : bool{
		return $volume->isInBounds($x, $y, $z) && ($y + self::DECK_Y + 4) < $volume->getMaxY();
	}

	public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		$this->originX = $x;
		$this->originY = $y;
		$this->originZ = $z;

		$c = self::CENTER;
		$deck = self::DECK_Y;
		$roof = $deck + 3;

		//log stilts at the four corners, rising from the ground to just under the deck
		foreach([[-3, -2], [-3, 2], [3, -2], [3, 2]] as [$a, $b]){
			for($dy = 0; $dy < $deck; ++$dy){
				$this->set($volume, $c + $a, $dy, $b, $this->logStateId);
			}
		}
		//deck floor
		for($a = -3; $a <= 3; ++$a){
			for($b = -2; $b <= 2; ++$b){
				$this->set($volume, $c + $a, $deck, $b, $this->planksStateId);
			}
		}
		//walls (2 tall) with a doorway on the -X wall, then a flat plank roof
		for($a = -3; $a <= 3; ++$a){
			for($b = -2; $b <= 2; ++$b){
				$onWall = abs($a) === 3 || abs($b) === 2;
				$doorway = $a === -3 && $b === 0;
				for($dy = $deck + 1; $dy <= $deck + 2; ++$dy){
					if($onWall && !$doorway){
						$this->set($volume, $c + $a, $dy, $b, $this->planksStateId);
					}else{
						$this->set($volume, $c + $a, $dy, $b, $this->airStateId);
					}
				}
				$this->set($volume, $c + $a, $roof, $b, $this->planksStateId);
			}
		}
		//fixed furnishings on the deck
		$this->set($volume, $c + 2, $deck + 1, 1, $this->cauldronStateId);
		$this->set($volume, $c + 2, $deck + 1, -1, $this->craftingTableStateId);
		//a fence post marking the open corner of the porch
		$this->set($volume, $c - 3, $deck + 1, -2, $this->fenceStateId);
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
