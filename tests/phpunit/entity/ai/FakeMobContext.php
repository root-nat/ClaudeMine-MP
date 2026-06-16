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

use pocketmine\entity\ai\memory\Memory;
use pocketmine\entity\ai\nav\GridNodeAccess;
use pocketmine\entity\ai\nav\NodeAccess;
use pocketmine\entity\ai\target\TargetCandidate;
use pocketmine\math\Vector3;
use function array_shift;
use function count;

/**
 * Recording {@link MobContext} for unit tests. Captures addMotion/jump/lookAt/attackEntity calls and serves a scripted
 * RNG sequence so behaviour decisions are deterministic and assertable without a live World.
 */
final class FakeMobContext implements MobContext{

	public Vector3 $position;
	public float $followRange = 16.0;
	public float $movementSpeed = 0.25;
	public bool $onGround = true;

	private Memory $memory;
	private NodeAccess $nodeAccess;

	/** @var float[] */
	private array $rngSequence;

	public int $jumpCalls = 0;
	/** @var Vector3[] */
	public array $lookAtCalls = [];
	/** @var array<int, array{float, float}> */
	public array $moveDirectionCalls = [];
	/** @var array<int, array{float, float, float}> */
	public array $motionCalls = [];
	/** @var TargetCandidate[] */
	public array $attackCalls = [];

	/**
	 * @param float[] $rngSequence values cycled through for getRandomFloat(); defaults to always 0.0
	 */
	public function __construct(?Vector3 $position = null, ?NodeAccess $nodeAccess = null, array $rngSequence = [0.0]){
		$this->position = $position ?? new Vector3(0.5, 1.0, 0.5);
		$this->memory = new Memory();
		$this->nodeAccess = $nodeAccess ?? new GridNodeAccess();
		$this->rngSequence = $rngSequence;
	}

	public function getPosition() : Vector3{
		return $this->position;
	}

	public function getMotion() : Vector3{
		return Vector3::zero();
	}

	public function addMotion(float $x, float $y, float $z) : void{
		$this->motionCalls[] = [$x, $y, $z];
	}

	public function jump() : void{
		++$this->jumpCalls;
	}

	public function lookAt(Vector3 $target) : void{
		$this->lookAtCalls[] = $target;
	}

	public function setMoveDirection(float $dx, float $dz) : void{
		$this->moveDirectionCalls[] = [$dx, $dz];
	}

	public function isOnGround() : bool{
		return $this->onGround;
	}

	public function getFollowRange() : float{
		return $this->followRange;
	}

	public function getMovementSpeed() : float{
		return $this->movementSpeed;
	}

	public function getNodeAccess() : NodeAccess{
		return $this->nodeAccess;
	}

	public function getMemory() : Memory{
		return $this->memory;
	}

	public function getRandomFloat() : float{
		if(count($this->rngSequence) === 0){
			return 0.0;
		}
		$value = array_shift($this->rngSequence);
		$this->rngSequence[] = $value; //cycle
		return $value;
	}

	public function getEntityId() : int{
		return 1;
	}

	public function attackEntity(TargetCandidate $target) : void{
		$this->attackCalls[] = $target;
	}
}
