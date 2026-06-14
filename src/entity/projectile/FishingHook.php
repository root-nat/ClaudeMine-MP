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

namespace pocketmine\entity\projectile;

use pocketmine\block\Block;
use pocketmine\block\Water;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\particle\BubbleParticle;
use pocketmine\world\particle\WaterParticle;
use function atan2;
use function cos;
use function max;
use function min;
use function mt_rand;
use function sin;
use function sqrt;
use const M_PI;

/**
 * The fishing bobber cast by a {@link \pocketmine\item\FishingRod}: it flies out, lands on water and bobs on the surface,
 * then after a random wait a "fish" swims toward it (a trail of water particles), and on arrival takes the bait (the
 * bobber dips amid a flurry of bubbles). Reeling in (re-using the rod) while it's biting yields a catch from {@link
 * FishingLoot}. The Bedrock client draws the line to the owner automatically from the OWNER_EID metadata the base entity
 * syncs. Modelled on the lure mechanic of Root-Studios/RootMine-MP.
 */
class FishingHook extends Throwable{

	private const MIN_WAIT_TICKS = 100;
	private const MAX_WAIT_TICKS = 420;
	/** Upward motion applied each tick while submerged, so the bobber rises to and bobs on the water surface. */
	private const UNDERWATER_MOTION_Y = 0.16;
	/** How close the approaching fish must get to the bobber to take the bait. */
	private const FISH_PROXIMITY = 0.15;
	private const FISH_ZIGZAG = 0.3;
	private const BITE_MIN_TICKS = 40;
	private const BITE_MAX_TICKS = 60;
	private const BITE_BUBBLES = 8;
	/** Despawn if the owner gets this far away (or leaves). */
	private const MAX_OWNER_DISTANCE_SQ = 33.0 * 33.0;

	private bool $attracted = false;
	private bool $caught = false;
	private bool $reeled = false;
	private int $waitTicks = self::MIN_WAIT_TICKS;
	private int $caughtTimer = 0;
	private ?Vector3 $fish = null;
	private float $fishYaw = 0.0;

	public static function getNetworkTypeId() : string{ return EntityIds::FISHING_HOOK; }

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.25, 0.25); }

	protected function getInitialGravity() : float{ return 0.05; }

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->waitTicks = self::randomWait();
	}

	private static function randomWait() : int{
		return mt_rand(self::MIN_WAIT_TICKS, self::MAX_WAIT_TICKS);
	}

	protected function onHitBlock(Block $blockHit, RayTraceResult $hitResult) : void{
		//unlike a thrown snowball, a bobber must NOT despawn when it lands - it rests and floats until reeled in
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		if($this->closed){
			return false;
		}
		$hasUpdate = parent::entityBaseTick($tickDiff);

		$owner = $this->getOwningEntity();
		if(!$owner instanceof Player || !$owner->isAlive() || $owner->getWorld() !== $this->getWorld() || $owner->location->distanceSquared($this->location) > self::MAX_OWNER_DISTANCE_SQ){
			$this->flagForDespawn();
			return $hasUpdate;
		}

		if($this->isUnderwater()){
			//rise to and bob on the surface; horizontal drift is killed so the bobber stays where it landed
			$this->motion = $this->motion->withComponents(0.0, self::UNDERWATER_MOTION_Y, 0.0);
			$hasUpdate = true;
		}

		$this->handleFishing($tickDiff);

		return true;
	}

	/**
	 * Drives the waiting -> fish-approaching -> bite -> escape cycle while the bobber rests on water. Resets whenever the
	 * bobber is not on water (still flying, or dragged onto land).
	 */
	private function handleFishing(int $tickDiff) : void{
		if(!$this->isOnWater()){
			$this->attracted = false;
			$this->caught = false;
			$this->fish = null;
			$this->waitTicks = self::randomWait();
			return;
		}

		if(!$this->attracted){
			if($this->waitTicks > 0){
				$this->waitTicks -= $tickDiff;
				return;
			}
			$this->spawnFish();
			$this->attracted = true;
			return;
		}

		if(!$this->caught){
			if(!$this->attractFish()){
				return;
			}
			$this->caughtTimer = mt_rand(self::BITE_MIN_TICKS, self::BITE_MAX_TICKS);
			$this->fishBites();
			$this->caught = true;
			return;
		}

		if($this->caughtTimer > 0){
			$this->caughtTimer -= $tickDiff;
			if(($this->ticksLived % 3) === 0){
				$this->spawnBubbles(2); //ongoing bite tell until the player reels in
			}
			return;
		}

		//the fish got away: reset and wait for another
		$this->attracted = false;
		$this->caught = false;
		$this->fish = null;
		$this->waitTicks = self::randomWait();
	}

	private function isOnWater() : bool{
		$pos = $this->location;
		$world = $this->getWorld();
		return $world->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ()) instanceof Water
			|| $world->getBlockAt($pos->getFloorX(), $pos->getFloorY() - 1, $pos->getFloorZ()) instanceof Water;
	}

	/** Spawns a virtual fish a few blocks from the bobber, aimed at it. */
	private function spawnFish() : void{
		$pos = $this->location;
		$offsetX = ((mt_rand(0, 120) / 100) + mt_rand(1, 4)) * (mt_rand(0, 1) === 0 ? -1 : 1);
		$offsetZ = ((mt_rand(0, 120) / 100) + mt_rand(1, 4)) * (mt_rand(0, 1) === 0 ? -1 : 1);
		$this->fish = new Vector3($pos->x + $offsetX, $pos->y, $pos->z + $offsetZ);
		$this->fishYaw = atan2($pos->z - $this->fish->z, $pos->x - $this->fish->x);
	}

	/**
	 * Swims the fish a step toward the bobber (with a wobbling heading) and leaves a water particle in its wake - the
	 * visible "fish approaching" trail. Returns true once the fish reaches the bobber.
	 */
	private function attractFish() : bool{
		if($this->fish === null){
			return false;
		}
		$pos = $this->location;
		$dx = $pos->x - $this->fish->x;
		$dz = $pos->z - $this->fish->z;
		$distance = sqrt(($dx * $dx) + ($dz * $dz));
		if($distance <= 0.0001){
			return true;
		}

		$yawDiff = atan2($dz, $dx) - $this->fishYaw;
		while($yawDiff > M_PI){
			$yawDiff -= M_PI * 2;
		}
		while($yawDiff < -M_PI){
			$yawDiff += M_PI * 2;
		}
		$this->fishYaw += $yawDiff * 0.25;
		$this->fishYaw += ((mt_rand(0, 1000) / 1000) - 0.5) * self::FISH_ZIGZAG;

		$speed = min(0.28, max(0.08, $distance * 0.12));
		$this->fish = new Vector3($this->fish->x + cos($this->fishYaw) * $speed, $this->fish->y, $this->fish->z + sin($this->fishYaw) * $speed);
		$this->getWorld()->addParticle($this->fish, new WaterParticle());

		$ndx = $pos->x - $this->fish->x;
		$ndz = $pos->z - $this->fish->z;
		return sqrt(($ndx * $ndx) + ($ndz * $ndz)) < self::FISH_PROXIMITY;
	}

	private function fishBites() : void{
		//the bobber dunks under as the fish takes the bait
		$this->motion = $this->motion->withComponents($this->motion->x, -0.35, $this->motion->z);
		$this->spawnBubbles(self::BITE_BUBBLES);
	}

	private function spawnBubbles(int $count) : void{
		$world = $this->getWorld();
		for($i = 0; $i < $count; ++$i){
			$world->addParticle($this->location->add((mt_rand(-100, 100) / 100) * 0.3, 0.05, (mt_rand(-100, 100) / 100) * 0.3), new BubbleParticle());
		}
	}

	public function isBiting() : bool{
		return $this->caught;
	}

	/**
	 * Reels the bobber in. If a fish was biting, a catch is pulled toward the owner and some XP drops.
	 */
	public function reelIn() : void{
		if($this->reeled){
			return; //flagForDespawn isn't immediate, so guard against a second click reeling the same bobber twice
		}
		$this->reeled = true;
		$owner = $this->getOwningEntity();
		if($this->caught && $owner instanceof Player){
			$world = $this->getWorld();
			$loot = FishingLoot::roll(mt_rand(0, 999) / 1000, mt_rand(0, 999) / 1000);
			$pull = $owner->getEyePos()->subtractVector($this->location)->normalize()->multiply(0.45);
			$world->dropItem($this->location->asVector3(), $loot, $pull, 0);
			$world->dropExperience($this->location->asVector3(), mt_rand(1, 6));
		}
		$this->flagForDespawn();
	}
}
