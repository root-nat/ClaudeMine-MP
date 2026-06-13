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
use pocketmine\entity\ai\nav\NodeAccess;
use pocketmine\entity\ai\target\TargetCandidate;
use pocketmine\math\Vector3;

/**
 * The narrow bridge the pure AI components depend on instead of the concrete Entity/World. {@link AbstractMob}
 * implements this against the real entity; tests implement a recording fake. Every world/entity touchpoint the AI needs
 * is funnelled through here.
 */
interface MobContext{

	public function getPosition() : Vector3;

	public function getMotion() : Vector3;

	/**
	 * Applies a horizontal movement force this tick (engine friction/gravity then act on it). Implementations forward to
	 * Entity::addMotion.
	 */
	public function addMotion(float $x, float $y, float $z) : void;

	/**
	 * Requests a jump; no-ops if the mob is not on the ground.
	 */
	public function jump() : void;

	public function lookAt(Vector3 $target) : void;

	/**
	 * Rotates the mob to face the horizontal direction it is moving in (body + head). Movement goals call this each tick
	 * so a walking mob turns to face its path instead of sliding sideways. No-ops for a zero vector. Implementations turn
	 * at a capped rate for a natural turn.
	 */
	public function setMoveDirection(float $dx, float $dz) : void;

	public function isOnGround() : bool;

	/**
	 * Bedrock follow_range attribute: the search radius for targets and pathing.
	 */
	public function getFollowRange() : float;

	/**
	 * Bedrock movement attribute used to scale walk force.
	 */
	public function getMovementSpeed() : float;

	public function getNodeAccess() : NodeAccess;

	public function getMemory() : Memory;

	/**
	 * Deterministic-enough RNG in [0, 1) for behaviour jitter. Implementations may vary the source.
	 */
	public function getRandomFloat() : float;

	public function getEntityId() : int;

	/**
	 * Performs a melee attack against the given target. Implementations forward to the entity's attack logic.
	 */
	public function attackEntity(TargetCandidate $target) : void;
}
