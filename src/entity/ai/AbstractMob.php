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

namespace pocketmine\entity\ai;

use pocketmine\entity\ai\goal\Goal;
use pocketmine\entity\ai\goal\GoalSelector;
use pocketmine\entity\ai\memory\Memory;
use pocketmine\entity\ai\nav\NodeAccess;
use pocketmine\entity\ai\nav\WorldNodeAccess;
use pocketmine\entity\ai\sensor\Sensor;
use pocketmine\entity\ai\target\TargetCandidate;
use pocketmine\entity\ai\target\TargetSelector;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Attribute;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use function abs;
use function count;
use function mt_getrandmax;
use function mt_rand;

/**
 * Base class for AI-driven mobs. Owns the goal selector, target selector, sensors and memory, implements
 * {@link MobContext} against the live entity, and drives the whole AI pipeline once per tick from entityBaseTick. New
 * mobs extend this and declare their behaviour in registerBehaviour(). The pure components it delegates to are all
 * unit-tested; this glue only wires them to the engine.
 */
abstract class AbstractMob extends Living implements MobContext{

	protected GoalSelector $goalSelector;
	protected TargetSelector $targetSelector;
	protected Memory $aiMemory;
	private ?WorldNodeAccess $nodeAccess = null;

	/** @var Sensor[] */
	private array $sensors = [];
	/** @var int[] */
	private array $sensorCountdowns = [];

	protected function getInitialDragMultiplier() : float{ return 0.02; }

	protected function getInitialGravity() : float{ return 0.08; }

	protected function initEntity(CompoundTag $nbt) : void{
		//Living::initEntity sets current health from getMaxHealth() (the default 20) before we raise the real max below.
		//Detect a fresh spawn (no saved health) so a mob whose default max differs from 20 can start at its true full HP
		//instead of being stuck at 20; loaded mobs keep their saved (possibly wounded) health.
		$freshSpawn = $nbt->getTag("Health") === null && $nbt->getTag("HealF") === null;
		parent::initEntity($nbt);
		$this->setStepHeight(1.0); //Bedrock mobs climb 1-block obstacles
		$max = $this->getDefaultMaxHealth();
		if($max !== $this->getMaxHealth()){
			$this->setMaxHealth($max);
			if($freshSpawn){
				$this->setHealth($max);
			}
		}
		$this->aiMemory = new Memory();
		$this->goalSelector = new GoalSelector();
		$this->targetSelector = new TargetSelector();
		$this->registerBehaviour();
	}

	/**
	 * The maximum health this mob spawns with. Override per species (e.g. 10 for a cow).
	 */
	protected function getDefaultMaxHealth() : int{
		return 20;
	}

	/**
	 * Declares this mob's goals and sensors via addGoal()/addSensor().
	 */
	abstract protected function registerBehaviour() : void;

	protected function addGoal(int $priority, Goal $goal) : void{
		$this->goalSelector->add($priority, $goal);
	}

	protected function addSensor(Sensor $sensor) : void{
		$this->sensors[] = $sensor;
		$this->sensorCountdowns[] = 0;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->closed || !$this->isAlive()){
			return $hasUpdate;
		}

		$this->aiMemory->tickExpiries($tickDiff);

		$sensorCount = count($this->sensors);
		for($i = 0; $i < $sensorCount; ++$i){
			$this->sensorCountdowns[$i] -= $tickDiff;
			if($this->sensorCountdowns[$i] <= 0){
				$this->sensorCountdowns[$i] = $this->sensors[$i]->getScanIntervalTicks();
				$this->sensors[$i]->sense($this, $this->aiMemory);
			}
		}

		$this->targetSelector->selectTarget($this);
		if($this->isMovementFrozen()){
			//a frozen mob (e.g. a piglin admiring a bartered ingot) holds its ground: skip its movement/attack goals and
			//damp any residual horizontal drift so it stops on the spot, keeping vertical motion for gravity
			$this->motion = $this->motion->withComponents(0.0, $this->motion->y, 0.0);
		}else{
			$this->goalSelector->tick($this);
		}

		return true;
	}

	/**
	 * Whether this mob should hold still this tick, ignoring its movement/attack goals (e.g. a piglin admiring a bartered
	 * gold ingot). Override per species; defaults to never frozen.
	 */
	protected function isMovementFrozen() : bool{
		return false;
	}

	public function getFollowRange() : float{
		$attr = $this->getAttributeMap()->get(Attribute::FOLLOW_RANGE);
		return $attr !== null ? $attr->getValue() : 16.0;
	}

	public function getMovementSpeed() : float{
		$attr = $this->getAttributeMap()->get(Attribute::MOVEMENT_SPEED);
		return $attr !== null ? $attr->getValue() : 0.25;
	}

	/**
	 * Maximum degrees the body may turn per tick when facing the movement direction. Override for faster/slower turners.
	 */
	protected function getMaxYawTurnPerTick() : float{
		return 30.0;
	}

	public function setMoveDirection(float $dx, float $dz) : void{
		if(abs($dx) < 1e-4 && abs($dz) < 1e-4){
			return;
		}
		$targetYaw = RotationHelper::directionToYaw($dx, $dz);
		$newYaw = RotationHelper::turnTowards($this->location->yaw, $targetYaw, $this->getMaxYawTurnPerTick());
		$this->setRotation($newYaw, $this->location->pitch);
	}

	public function getNodeAccess() : NodeAccess{
		if($this->nodeAccess === null){
			$this->nodeAccess = new WorldNodeAccess($this->getWorld());
		}
		return $this->nodeAccess;
	}

	public function getMemory() : Memory{
		return $this->aiMemory;
	}

	public function getRandomFloat() : float{
		return mt_rand() / mt_getrandmax();
	}

	public function getEntityId() : int{
		return $this->getId();
	}

	public function attackEntity(TargetCandidate $target) : void{
		$victim = $this->getWorld()->getEntity($target->entityId);
		if($victim instanceof Living && $victim->isAlive()){
			$this->broadcastAnimation(new ArmSwingAnimation($this)); //play the mob's melee swing on the client
			$damageAttr = $this->getAttributeMap()->get(Attribute::ATTACK_DAMAGE);
			$damage = $damageAttr !== null ? $damageAttr->getValue() : 2.0;
			$victim->attack(new EntityDamageByEntityEvent($this, $victim, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage));
		}
	}
}
