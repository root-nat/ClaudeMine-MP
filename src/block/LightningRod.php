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

namespace pocketmine\block;

use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\AnyFacingTrait;
use pocketmine\block\utils\CopperMaterial;
use pocketmine\block\utils\CopperTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\World;
use function floor;

final class LightningRod extends Transparent implements AnyFacing, CopperMaterial{
	use CopperTrait;
	use AnyFacingTrait;

	/** How long the rod stays powered after being struck. */
	private const ACTIVE_TICKS = 8;
	/** Bounded search radius for attracting a nearby strike to the highest rod (vanilla attracts from farther). */
	private const ATTRACT_RADIUS = 16;

	protected bool $powered = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
		$w->bool($this->powered);
	}

	public function isPowered() : bool{ return $this->powered; }

	/** @return $this */
	public function setPowered(bool $powered) : self{
		$this->powered = $powered;
		return $this;
	}

	protected function recalculateCollisionBoxes() : array{
		$myAxis = Facing::axis($this->facing);

		$result = AxisAlignedBB::one();
		foreach([Axis::X, Axis::Y, Axis::Z] as $axis){
			if($axis !== $myAxis){
				$result->squash($axis, 6 / 16);
			}
		}

		return [$result];
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->facing = $face;
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function getWeakRedstonePower(int $face) : int{
		return $this->powered ? 15 : 0;
	}

	public function getStrongRedstonePower(int $face) : int{
		return $this->powered ? 15 : 0;
	}

	public function isRedstoneConductor() : bool{
		return false;
	}

	/**
	 * Energises the rod for a few ticks (emitting redstone) after it intercepts a lightning bolt.
	 */
	public function onStruckByLightning() : void{
		$world = $this->position->getWorld();
		$this->powered = true;
		$world->setBlock($this->position, $this);
		$world->scheduleDelayedBlockUpdate($this->position, self::ACTIVE_TICKS);
	}

	public function onScheduledUpdate() : void{
		if($this->powered){
			$this->powered = false;
			$this->position->getWorld()->setBlock($this->position, $this);
		}
	}

	/**
	 * Finds the highest lightning rod within {@link self::ATTRACT_RADIUS} of a strike position, so the strike can be
	 * redirected to it. Returns null if there is none nearby.
	 */
	public static function findStruckRod(World $world, Vector3 $pos) : ?LightningRod{
		$cx = (int) floor($pos->x);
		$cy = (int) floor($pos->y);
		$cz = (int) floor($pos->z);

		$best = null;
		$bestY = null;
		for($x = $cx - self::ATTRACT_RADIUS; $x <= $cx + self::ATTRACT_RADIUS; $x++){
			for($z = $cz - self::ATTRACT_RADIUS; $z <= $cz + self::ATTRACT_RADIUS; $z++){
				for($y = $cy + self::ATTRACT_RADIUS; $y >= $cy - self::ATTRACT_RADIUS; $y--){
					$block = $world->getBlockAt($x, $y, $z);
					if($block instanceof LightningRod){
						if($bestY === null || $y > $bestY){
							$best = $block;
							$bestY = $y;
						}
						break; //highest rod in this column found; move on
					}
				}
			}
		}
		return $best;
	}
}
