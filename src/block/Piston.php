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

	private function buildReactionResolver(World $world) : \Closure{
		return function(Vector3 $pos) use ($world) : int{
			$block = $world->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
			$typeId = $block->getTypeId();
			if($typeId === BlockTypeIds::AIR){
				return PistonStructureResolver::REACTION_AIR;
			}
			if($block instanceof Piston || $block instanceof PistonArmCollision){
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

		/** @var array{Vector3, Block}[] $captured */
		$captured = [];
		foreach($move as $pos){
			$captured[] = [$pos, $world->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ())];
		}
		foreach($move as $pos){
			$world->setBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), VanillaBlocks::AIR());
		}
		foreach($captured as [$pos, $block]){
			$dest = $pos->getSide($this->facing);
			$world->setBlockAt($dest->getFloorX(), $dest->getFloorY(), $dest->getFloorZ(), $block);
		}

		$world->setBlock($origin, $this->getArmCollisionBlock()->setFacing($this->facing));
		$world->addSound($this->position->add(0.5, 0.5, 0.5), new RedstonePowerOnSound());
		return true;
	}

	private function retract() : void{
		$world = $this->position->getWorld();
		$origin = $this->position->getSide($this->facing);
		if(!$world->getBlock($origin) instanceof PistonArmCollision){
			return;
		}

		if($this->isSticky()){
			$pullPos = $origin->getSide($this->facing);
			$reaction = ($this->buildReactionResolver($world))($pullPos);
			if($reaction === PistonStructureResolver::REACTION_NORMAL || $reaction === PistonStructureResolver::REACTION_STICKY){
				$pulled = $world->getBlock($pullPos);
				$world->setBlock($pullPos, VanillaBlocks::AIR());
				$world->setBlock($origin, $pulled);
				$world->addSound($this->position->add(0.5, 0.5, 0.5), new RedstonePowerOffSound());
				return;
			}
		}

		$world->setBlock($origin, VanillaBlocks::AIR());
		$world->addSound($this->position->add(0.5, 0.5, 0.5), new RedstonePowerOffSound());
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
