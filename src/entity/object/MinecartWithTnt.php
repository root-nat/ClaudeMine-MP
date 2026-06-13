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

namespace pocketmine\entity\object;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Explosive;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\Explosion;
use pocketmine\world\Position;

class MinecartWithTnt extends AbstractMinecart implements Explosive{

	private bool $primed = false;
	private int $fuse = 80;

	public static function getNetworkTypeId() : string{ return EntityIds::TNT_MINECART; }

	public function getName() : string{
		return "Minecart with TNT";
	}

	protected function getMinecartItem() : Item{
		return VanillaBlocks::TNT()->asItem();
	}

	public function attack(EntityDamageEvent $source) : void{
		if(
			$source instanceof EntityDamageByEntityEvent &&
			($source->getCause() === EntityDamageEvent::CAUSE_FIRE || $source->getCause() === EntityDamageEvent::CAUSE_FIRE_TICK)
		){
			$this->prime();
		}
		parent::attack($source);
	}

	public function prime(int $fuse = 80) : void{
		$this->primed = true;
		$this->fuse = $fuse;
		$this->networkPropertiesDirty = true;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->primed && !$this->isFlaggedForDespawn()){
			$this->fuse -= $tickDiff;
			if($this->fuse <= 0){
				$this->flagForDespawn();
				$this->explode();
			}
			$hasUpdate = true;
		}
		return $hasUpdate;
	}

	public function explode() : void{
		$ev = new EntityPreExplodeEvent($this, 4.0);
		$ev->call();
		if(!$ev->isCancelled()){
			$explosion = new Explosion(Position::fromObject($this->location, $this->getWorld()), $ev->getRadius(), $this, $ev->getFireChance());
			if($ev->isBlockBreaking()){
				$explosion->explodeA();
			}
			$explosion->explodeB();
		}
	}

	protected function destroyCart(bool $dropItems) : void{
		//destroying a TNT minecart by hand drops the item; lethal damage primes it instead in vanilla, but we keep the
		//drop behaviour for survivability
		parent::destroyCart($dropItems);
	}
}
