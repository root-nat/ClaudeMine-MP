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

namespace pocketmine\item;

use pocketmine\block\Block;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\FishingHook;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\ThrowSound;

class FishingRod extends Durable{

	private const CAST_FORCE = 1.1;

	public function getMaxStackSize() : int{
		return 1;
	}

	public function getMaxDurability() : int{
		return 384;
	}

	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		return $this->castOrReel($player, $directionVector);
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		return $this->castOrReel($player, $player->getDirectionVector());
	}

	private function castOrReel(Player $player, Vector3 $directionVector) : ItemUseResult{
		$hook = $this->findHook($player);
		if($hook !== null){
			$hook->reelIn();
			$this->applyDamage(1);
			return ItemUseResult::SUCCESS;
		}

		$world = $player->getWorld();
		$location = $player->getLocation();
		$bobber = new FishingHook(Location::fromObject($player->getEyePos(), $world, $location->yaw, $location->pitch), $player);
		$bobber->setMotion($directionVector->multiply(self::CAST_FORCE));

		$ev = new ProjectileLaunchEvent($bobber);
		$ev->call();
		if($ev->isCancelled()){
			$bobber->flagForDespawn();
			return ItemUseResult::FAIL;
		}

		$bobber->spawnToAll();
		$world->addSound($player->getEyePos(), new ThrowSound());
		$this->applyDamage(1);
		return ItemUseResult::SUCCESS;
	}

	private function findHook(Player $player) : ?FishingHook{
		foreach($player->getWorld()->getEntities() as $entity){
			if($entity instanceof FishingHook && $entity->getOwningEntity() === $player){
				return $entity;
			}
		}
		return null;
	}
}
