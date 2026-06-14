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

use pocketmine\block\tile\MovingBlock as MovingBlockTile;
use pocketmine\block\tile\PistonArmCollision as PistonArmCollisionTile;
use pocketmine\block\tile\Spawnable;
use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\AnyFacingTrait;
use pocketmine\block\utils\PistonStructureResolver;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\sound\RedstonePowerOffSound;
use pocketmine\world\sound\RedstonePowerOnSound;
use pocketmine\world\World;

class Piston extends Opaque implements AnyFacing{
	use AnyFacingTrait;

	private const ACTION_DELAY_TICKS = 2;

	public function isSticky() : bool{
		return false;
	}

	protected function getArmCollisionBlock() : PistonArmCollision{
		return VanillaBlocks::PISTON_ARM_COLLISION();
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			$this->facing = Facing::opposite($player->getHorizontalFacing());
			$pitch = $player->getLocation()->getPitch();
			if($pitch > 48){
				$this->facing = Facing::UP;
			}elseif($pitch < -48){
				$this->facing = Facing::DOWN;
			}
		}
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onPostPlace() : void{
		$tile = $this->getPistonTile();
		if($tile !== null){
			$tile->setSticky($this->isSticky());
		}
		$this->onNearbyBlockChange();
	}

	public function onNearbyBlockChange() : void{
		$powered = $this->isReceivingRedstonePower();
		$extended = $this->isExtended();
		if($powered !== $extended){
			$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, self::ACTION_DELAY_TICKS);
		}
	}

	public function onScheduledUpdate() : void{
		$tile = $this->getPistonTile();
		if($tile !== null && $tile->isMoving()){
			//the animation window elapsed: commit the block movement and settle the state
			if($tile->getState() === PistonArmCollisionTile::STATE_EXTENDING){
				$this->finishExtend($tile);
			}else{
				$this->finishRetract($tile);
			}
			return;
		}

		$powered = $this->isReceivingRedstonePower();
		$extended = $this->isExtended();
		if($powered && !$extended){
			$this->extend();
		}elseif(!$powered && $extended){
			$this->retract();
		}
	}

	public function isExtended() : bool{
		return $this->getSide($this->facing) instanceof PistonArmCollision;
	}

	private function getPistonTile() : ?PistonArmCollisionTile{
		$tile = $this->position->getWorld()->getTile($this->position);
		return $tile instanceof PistonArmCollisionTile ? $tile : null;
	}

	/**
	 * Re-sends a tile's spawn NBT to clients by invalidating its cache and marking the block changed (no neighbour
	 * updates, so a mid-slide block doesn't trigger redstone/gravity).
	 */
	private function resyncTile(Vector3 $pos) : void{
		$world = $this->position->getWorld();
		$tile = $world->getTile($pos);
		if($tile instanceof Spawnable){
			$tile->clearSpawnCompoundCache();
		}
		$world->setBlock($pos, $world->getBlock($pos), false);
	}

	/**
	 * @phpstan-return \Closure(Vector3) : PistonStructureResolver::REACTION_*
	 */
	private function buildReactionResolver(World $world) : \Closure{
		return function(Vector3 $pos) use ($world) : int{
			$block = $world->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
			$typeId = $block->getTypeId();
			if($typeId === BlockTypeIds::AIR){
				return PistonStructureResolver::REACTION_AIR;
			}
			if($block instanceof Piston || $block instanceof PistonArmCollision || $block instanceof MovingBlock){
				return PistonStructureResolver::REACTION_BLOCK;
			}
			if(!$block->getBreakInfo()->isBreakable() || $typeId === BlockTypeIds::OBSIDIAN){
				return PistonStructureResolver::REACTION_BLOCK;
			}
			if($world->getTile($pos) !== null){
				return PistonStructureResolver::REACTION_BLOCK;
			}
			if($block->canBeReplaced()){
				return PistonStructureResolver::REACTION_DESTROY;
			}
			if($block->isPistonSticky()){
				return PistonStructureResolver::REACTION_STICKY;
			}
			return PistonStructureResolver::REACTION_NORMAL;
		};
	}

	private function extend() : bool{
		$world = $this->position->getWorld();
		$origin = $this->position->getSide($this->facing);

		$result = PistonStructureResolver::computeMovement($origin, $this->position, $this->facing, $this->buildReactionResolver($world));
		if($result === null){
			return false;
		}
		[$move, $destroy] = $result;

		foreach($destroy as $pos){
			$world->useBreakOn($pos);
		}

		$tile = $this->getPistonTile();
		if($tile === null){
			//legacy piston without an animation tile: fall back to an instant move so it still works
			$this->instantExtend($move, $origin);
			return true;
		}

		//turn each pushed block into a sliding moving_block at its CURRENT position; the real blocks are placed at their
		//destinations only when the animation finishes, so the client renders them sliding toward the destination.
		$attached = [];
		foreach($move as $pos){
			$block = $world->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
			$world->setBlock($pos, VanillaBlocks::MOVING_BLOCK(), false);
			$moving = $world->getTile($pos);
			if($moving instanceof MovingBlockTile){
				$moving->setMovingBlock($block);
				$moving->setPistonPosition($this->position);
				$this->resyncTile($pos);
			}
			$attached[] = $pos;
		}

		$tile->setSticky($this->isSticky());
		$tile->startMovement(PistonArmCollisionTile::STATE_EXTENDING, 0.0, $attached, []);
		$this->resyncTile($this->position);

		$world->addSound($this->position->add(0.5, 0.5, 0.5), new RedstonePowerOnSound());
		$world->scheduleDelayedBlockUpdate($this->position, self::ACTION_DELAY_TICKS);
		return true;
	}

	private function finishExtend(PistonArmCollisionTile $tile) : void{
		$world = $this->position->getWorld();
		$facing = $this->facing;

		$captured = [];
		foreach($tile->getAttachedBlocks() as $pos){
			$moving = $world->getTile($pos);
			$block = $moving instanceof MovingBlockTile ? $moving->getMovingBlock() : null;
			if($block !== null){
				$captured[] = [$pos->getSide($facing), $block];
			}
			$world->setBlock($pos, VanillaBlocks::AIR(), false);
		}
		foreach($captured as [$dest, $block]){
			$world->setBlock($dest, $block, false);
		}

		$world->setBlock($this->position->getSide($facing), $this->getArmCollisionBlock()->setFacing($facing));
		$tile->finishMovement(PistonArmCollisionTile::STATE_EXTENDED);
		$this->resyncTile($this->position);
	}

	/**
	 * @param Vector3[] $move
	 */
	private function instantExtend(array $move, Vector3 $origin) : void{
		$world = $this->position->getWorld();
		$captured = [];
		foreach($move as $pos){
			$captured[] = [$pos, $world->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ())];
		}
		foreach($move as $pos){
			$world->setBlock($pos, VanillaBlocks::AIR());
		}
		foreach($captured as [$pos, $block]){
			$world->setBlock($pos->getSide($this->facing), $block);
		}
		$world->setBlock($origin, $this->getArmCollisionBlock()->setFacing($this->facing));
		$world->addSound($this->position->add(0.5, 0.5, 0.5), new RedstonePowerOnSound());
	}

	private function retract() : void{
		$world = $this->position->getWorld();
		$armPos = $this->position->getSide($this->facing);
		if(!$world->getBlock($armPos) instanceof PistonArmCollision){
			return;
		}
		$world->setBlock($armPos, VanillaBlocks::AIR(), false);

		$attached = [];
		if($this->isSticky()){
			$pullPos = $armPos->getSide($this->facing);
			$reaction = ($this->buildReactionResolver($world))($pullPos);
			if($reaction === PistonStructureResolver::REACTION_NORMAL || $reaction === PistonStructureResolver::REACTION_STICKY){
				$pulled = $world->getBlock($pullPos);
				$world->setBlock($pullPos, VanillaBlocks::MOVING_BLOCK(), false);
				$moving = $world->getTile($pullPos);
				if($moving instanceof MovingBlockTile){
					$moving->setMovingBlock($pulled);
					$moving->setPistonPosition($this->position);
					$this->resyncTile($pullPos);
				}
				$attached[] = $pullPos;
			}
		}

		$tile = $this->getPistonTile();
		if($tile === null){
			//legacy piston without an animation tile: settle instantly
			$this->finishRetractPositions($attached);
			$world->addSound($this->position->add(0.5, 0.5, 0.5), new RedstonePowerOffSound());
			return;
		}

		$tile->setSticky($this->isSticky());
		$tile->startMovement(PistonArmCollisionTile::STATE_RETRACTING, 1.0, $attached, []);
		$this->resyncTile($this->position);

		$world->addSound($this->position->add(0.5, 0.5, 0.5), new RedstonePowerOffSound());
		$world->scheduleDelayedBlockUpdate($this->position, self::ACTION_DELAY_TICKS);
	}

	private function finishRetract(PistonArmCollisionTile $tile) : void{
		$this->finishRetractPositions($tile->getAttachedBlocks());
		$tile->finishMovement(PistonArmCollisionTile::STATE_RETRACTED);
		$this->resyncTile($this->position);
	}

	/**
	 * @param Vector3[] $positions
	 */
	private function finishRetractPositions(array $positions) : void{
		$world = $this->position->getWorld();
		$opposite = Facing::opposite($this->facing);
		$captured = [];
		foreach($positions as $pos){
			$moving = $world->getTile($pos);
			$block = $moving instanceof MovingBlockTile ? $moving->getMovingBlock() : null;
			if($block !== null){
				$captured[] = [$pos->getSide($opposite), $block];
			}
			$world->setBlock($pos, VanillaBlocks::AIR(), false);
		}
		foreach($captured as [$dest, $block]){
			$world->setBlock($dest, $block, false);
		}
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		$extended = $this->isExtended();
		$armPos = $this->position->getSide($this->facing);
		if(parent::onBreak($item, $player, $returnedItems)){
			if($extended){
				$world = $this->position->getWorld();
				if($world->getBlock($armPos) instanceof PistonArmCollision){
					$world->setBlock($armPos, VanillaBlocks::AIR());
				}
			}
			return true;
		}
		return false;
	}
}
