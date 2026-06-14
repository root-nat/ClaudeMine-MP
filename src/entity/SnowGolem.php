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
use pocketmine\block\utils\SupportType;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\ai\AbstractMob;
use pocketmine\entity\ai\goal\RandomStrollGoal;
use pocketmine\entity\ai\goal\RangedAttackGoal;
use pocketmine\entity\ai\sensor\NearestMonstersSensor;
use pocketmine\entity\ai\target\TargetCandidate;
use pocketmine\entity\projectile\Snowball;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\Position;
use function mt_rand;
use function sqrt;

/**
 * A built snow guardian: stack two snow blocks and cap them with a (carved or lit) pumpkin and this springs up. It pelts
 * nearby hostile monsters with harmless-but-annoying snowballs (knockback only) and never targets players, but melts away
 * in water. Build it with {@link self::tryBuild()}.
 */
class SnowGolem extends AbstractMob{

	public static function getNetworkTypeId() : string{ return EntityIds::SNOW_GOLEM; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.7);
	}

	protected function getDefaultMaxHealth() : int{
		return 4;
	}

	public function getName() : string{
		return "Snow Golem";
	}

	protected function registerBehaviour() : void{
		$this->addSensor(new NearestMonstersSensor($this->getFollowRange()));
		$this->addGoal(2, new RangedAttackGoal(fn(TargetCandidate $target) => $this->shootSnowballAt($target), 10.0, 4.0, 20));
		$this->addGoal(8, new RandomStrollGoal());
	}

	private function shootSnowballAt(TargetCandidate $target) : void{
		$origin = $this->getEyePos();
		$snowball = new Snowball(Location::fromObject($origin, $this->getWorld(), $this->location->getYaw(), $this->location->getPitch()), $this);

		$dx = $target->x - $origin->x;
		$dy = ($target->y + 1.0) - $origin->y;
		$dz = $target->z - $origin->z;
		$horizontal = sqrt(($dx * $dx) + ($dz * $dz));
		$direction = new Vector3($dx, $dy + $horizontal * 0.2, $dz);

		$snowball->setMotion($direction->normalize()->multiply(1.6));
		$snowball->spawnToAll();
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->closed || !$this->isAlive()){
			return $hasUpdate;
		}

		//snow golems melt away in water - CAUSE_CUSTOM so it never gets cancelled by the drowningDamage gamerule
		//(commonly disabled on skyblock/creative worlds), unlike vanilla-independent snow-golem melting
		if($this->isUnderwater() && ($this->ticksLived % 10) === 0){
			$this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_CUSTOM, 1));
		}

		$this->layTrailSnow();

		return $hasUpdate;
	}

	/**
	 * Leaves a layer of snow on the block under the golem's feet as it walks, the basis of the classic snow farm. Only in
	 * cold-enough biomes (where snow settles) and on a full solid top, matching vanilla.
	 */
	private function layTrailSnow() : void{
		if(!$this->onGround){
			return;
		}
		$world = $this->getWorld();
		$feetPos = $this->location->floor();
		if($world->getBiome($feetPos->getFloorX(), $feetPos->getFloorY(), $feetPos->getFloorZ())->getTemperature() >= 1.0){
			return; //hot biome (desert/badlands): snow can't settle (and a vanilla golem would be melting here)
		}
		if($world->getBlock($feetPos)->getTypeId() !== BlockTypeIds::AIR){
			return;
		}
		if($world->getBlock($feetPos->down())->getSupportType(Facing::UP) !== SupportType::FULL){
			return;
		}
		$world->setBlock($feetPos, VanillaBlocks::SNOW_LAYER());
	}

	public function getDrops() : array{
		return [VanillaItems::SNOWBALL()->setCount(mt_rand(0, 15))];
	}

	public function getXpDropAmount() : int{
		return 0;
	}

	public function getPickedItem() : ?Item{
		return null;
	}

	/**
	 * Tries to spawn a snow golem from the pumpkin block just placed at $pumpkin: two snow blocks stacked directly below
	 * it are consumed and a golem rises in their place. Returns whether one was built.
	 */
	public static function tryBuild(Position $pumpkin) : bool{
		$world = $pumpkin->getWorld();
		$body = $pumpkin->down();
		$base = $body->down();
		if($world->getBlock($body)->getTypeId() !== BlockTypeIds::SNOW || $world->getBlock($base)->getTypeId() !== BlockTypeIds::SNOW){
			return false;
		}

		$air = VanillaBlocks::AIR();
		$world->setBlock($pumpkin->asVector3(), $air);
		$world->setBlock($body, $air);
		$world->setBlock($base, $air);

		$golem = new SnowGolem(Location::fromObject($base->add(0.5, 0.0, 0.5), $world));
		$golem->spawnToAll();
		return true;
	}
}
