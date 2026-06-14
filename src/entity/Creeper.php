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

namespace pocketmine\entity;

use pocketmine\entity\ai\CreeperSwellLogic;
use pocketmine\entity\ai\goal\AvoidEntityGoal;
use pocketmine\entity\ai\goal\MeleeAttackGoal;
use pocketmine\entity\ai\memory\MemoryModuleType;
use pocketmine\entity\ai\sensor\AvoidEntitySensor;
use pocketmine\entity\ai\target\TargetCandidate;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\world\Explosion;
use pocketmine\world\Position;
use function sqrt;

class Creeper extends Monster implements Explosive{

	private const TAG_FUSE = "Fuse"; //TAG_Short
	/** Creepers flee any cat within this many blocks. */
	private const CAT_AVOID_RANGE = 6.0;

	private int $fuse = 0;

	public static function getNetworkTypeId() : string{ return EntityIds::CREEPER; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.7, 0.6);
	}

	public function getName() : string{
		return "Creeper";
	}

	protected function registerAttackGoals() : void{
		//creepers are terrified of cats: flee a nearby one (and don't swell, see entityBaseTick) instead of attacking.
		//Scan every tick (like HurtBySensor) so a cat is noticed before an already-swelling fuse can reach detonation.
		$this->addSensor(new AvoidEntitySensor(Cat::class, self::CAT_AVOID_RANGE, 1));
		$this->addGoal(1, new AvoidEntityGoal());
		//the creeper just walks up to its target (MeleeAttackGoal handles the approach); it never bites - see attackEntity
		//and the swell/detonation in entityBaseTick
		$this->addGoal(2, new MeleeAttackGoal());
	}

	public function attackEntity(TargetCandidate $target) : void{
		//creepers don't deal melee damage in vanilla - they only swell up and explode (entityBaseTick handles that),
		//so the approach goal's "hit" must do nothing (no bite, no arm swing)
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->closed || !$this->isAlive()){
			return $hasUpdate;
		}

		//a creeper fleeing a cat backs off without swelling, so freeze the fuse while it has something to avoid
		$avoiding = $this->getMemory()->get(MemoryModuleType::AVOID_TARGET) instanceof TargetCandidate;
		$target = $this->getMemory()->get(MemoryModuleType::ATTACK_TARGET);
		$distance = null;
		if(!$avoiding && $target instanceof TargetCandidate && $target->alive){
			$pos = $this->location;
			$distance = sqrt($target->distanceSquaredTo($pos->x, $pos->y, $pos->z));
		}

		$oldFuse = $this->fuse;
		$this->fuse = CreeperSwellLogic::updateFuse($this->fuse, $distance);
		if($this->fuse !== $oldFuse){
			$this->networkPropertiesDirty = true;
			$hasUpdate = true;
		}
		if(CreeperSwellLogic::shouldExplode($this->fuse)){
			$this->flagForDespawn();
			$this->explode();
		}

		return $hasUpdate;
	}

	public function explode() : void{
		$ev = new EntityPreExplodeEvent($this, 3.0);
		$ev->call();
		if(!$ev->isCancelled()){
			$explosion = new Explosion(Position::fromObject($this->location, $this->getWorld()), $ev->getRadius(), $this, $ev->getFireChance());
			if($ev->isBlockBreaking()){
				$explosion->explodeA();
			}
			$explosion->explodeB();
		}
	}

	public function getDrops() : array{
		return [VanillaItems::GUNPOWDER()->setCount(\mt_rand(0, 2))];
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::CREEPER_SPAWN_EGG();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::FUSE_LENGTH, $this->fuse);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setShort(self::TAG_FUSE, $this->fuse);
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->fuse = $nbt->getShort(self::TAG_FUSE, 0);
	}
}
