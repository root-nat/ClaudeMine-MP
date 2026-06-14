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

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\ai\AbstractMob;
use pocketmine\entity\ai\goal\MeleeAttackGoal;
use pocketmine\entity\ai\goal\RandomStrollGoal;
use pocketmine\entity\ai\sensor\NearestMonstersSensor;
use pocketmine\entity\ai\target\TargetCandidate;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\Position;
use function mt_rand;

/**
 * A built guardian: stomp out a frame of four iron blocks and cap it with a (carved or lit) pumpkin and one of these
 * spawns. It patrols the area and pummels nearby hostile monsters with a heavy, knock-them-skyward punch, but never turns
 * on players. Tanky and slow. Build it with {@link self::tryBuild()} from the pumpkin that completes the frame.
 */
class IronGolem extends AbstractMob{

	public static function getNetworkTypeId() : string{ return EntityIds::IRON_GOLEM; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(2.7, 1.4);
	}

	protected function getDefaultMaxHealth() : int{
		return 100;
	}

	public function getName() : string{
		return "Iron Golem";
	}

	protected function registerBehaviour() : void{
		$this->addSensor(new NearestMonstersSensor($this->getFollowRange()));
		$this->addGoal(2, new MeleeAttackGoal());
		$this->addGoal(8, new RandomStrollGoal());
	}

	public function attackEntity(TargetCandidate $target) : void{
		$victim = $this->getWorld()->getEntity($target->entityId);
		if($victim instanceof Living && $victim->isAlive()){
			$this->broadcastAnimation(new ArmSwingAnimation($this)); //swing the arms up like vanilla
			$victim->attack(new EntityDamageByEntityEvent($this, $victim, EntityDamageEvent::CAUSE_ENTITY_ATTACK, mt_rand(7, 21)));
			//the golem's signature upward toss, on top of the normal knockback
			$victim->addMotion(0, 0.4, 0);
		}
	}

	public function getDrops() : array{
		return [
			VanillaItems::IRON_INGOT()->setCount(mt_rand(3, 5)),
			VanillaBlocks::POPPY()->asItem()->setCount(mt_rand(0, 2)),
		];
	}

	public function getXpDropAmount() : int{
		return 0;
	}

	public function getPickedItem() : ?Item{
		return null;
	}

	/**
	 * Tries to spawn an iron golem from the pumpkin block just placed at $pumpkin. If the iron frame below it is complete,
	 * the four iron blocks and the pumpkin are consumed and a golem rises in their place. Returns whether one was built.
	 */
	public static function tryBuild(Position $pumpkin) : bool{
		$world = $pumpkin->getWorld();
		$body = $pumpkin->down();
		$base = $body->down();
		$isIron = fn(Vector3 $v) : bool => $world->getBlock($v)->getTypeId() === BlockTypeIds::IRON;

		$axis = IronGolemBuildLogic::matchedAxis(
			$isIron($body), $isIron($base),
			$isIron($body->west()), $isIron($body->east()),
			$isIron($body->north()), $isIron($body->south())
		);
		if($axis === null){
			return false;
		}

		//consume the pumpkin, the body+base, and every iron arm (both pairs if a full plus was built, so none is refunded)
		$air = VanillaBlocks::AIR();
		$world->setBlock($pumpkin->asVector3(), $air);
		$world->setBlock($body, $air);
		$world->setBlock($base, $air);
		foreach([$body->west(), $body->east(), $body->north(), $body->south()] as $arm){
			if($isIron($arm)){
				$world->setBlock($arm, $air);
			}
		}

		$golem = new IronGolem(Location::fromObject($base->add(0.5, 0.0, 0.5), $world));
		$golem->spawnToAll();
		return true;
	}
}
