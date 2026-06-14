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

use pocketmine\entity\ai\AbstractMob;
use pocketmine\entity\ai\goal\BreedGoal;
use pocketmine\entity\ai\goal\FollowParentGoal;
use pocketmine\entity\ai\goal\LookAtPlayerGoal;
use pocketmine\entity\ai\goal\PanicGoal;
use pocketmine\entity\ai\goal\RandomStrollGoal;
use pocketmine\entity\ai\goal\TemptGoal;
use pocketmine\entity\ai\sensor\BreedingSensor;
use pocketmine\entity\ai\sensor\HurtBySensor;
use pocketmine\entity\ai\sensor\NearestPlayersSensor;
use pocketmine\entity\ai\sensor\TemptingPlayerSensor;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\Particle;
use function intdiv;
use function max;
use function mt_rand;

/**
 * Base class for passive farm-style animals (cows, pigs, sheep, chickens...). Provides the common idle/panic/look
 * behaviour, the Bedrock baby state, and vanilla breeding (feed two adults their food to spawn a baby that grows up)
 * on top of the {@link AbstractMob} AI core. Species add their own size, drops, breeding food and any extra goals.
 *
 * @phpstan-consistent-constructor
 */
abstract class Animal extends AbstractMob implements Ageable{

	private const TAG_BABY = "Baby"; //TAG_Byte
	private const TAG_IN_LOVE = "InLove"; //TAG_Int
	private const TAG_BREED_COOLDOWN = "BreedCooldown"; //TAG_Int
	private const TAG_BABY_GROW = "BabyGrowTicks"; //TAG_Int

	protected bool $baby = false;
	protected int $inLoveTicks = 0;
	protected int $breedCooldownTicks = 0;
	protected int $babyGrowTicks = 0;

	public function isBaby() : bool{
		return $this->baby;
	}

	public function setBaby(bool $baby = true) : void{
		$this->baby = $baby;
		if($baby){
			$this->babyGrowTicks = BreedingHelper::BABY_GROW_TICKS;
		}
		$this->networkPropertiesDirty = true;
		//getInitialSizeInfo() depends on the baby flag, so re-apply the size to update the hitbox
		$this->setSize($this->getInitialSizeInfo());
	}

	/**
	 * The species key (see {@link BreedingHelper}) used for breeding-food lookup, or null if this animal can't be bred.
	 */
	protected function getBreedingSpecies() : ?string{
		return null;
	}

	public function isBreedingFood(Item $item) : bool{
		$species = $this->getBreedingSpecies();
		return $species !== null && !$item->isNull() && BreedingHelper::isFood($species, $item->getTypeId());
	}

	public function isInLove() : bool{
		return $this->inLoveTicks > 0;
	}

	public function setInLove() : void{
		$this->inLoveTicks = BreedingHelper::IN_LOVE_TICKS;
		$this->networkPropertiesDirty = true;
		$this->spawnParticleCloud(new HeartParticle());
	}

	protected function registerBehaviour() : void{
		$this->addSensor(new HurtBySensor());
		$this->addSensor(new NearestPlayersSensor($this->getFollowRange()));
		$this->addSensor(new TemptingPlayerSensor($this->getFollowRange()));
		$this->addSensor(new BreedingSensor($this->getFollowRange()));

		$this->addGoal(1, new PanicGoal());
		$this->addGoal(2, new BreedGoal());
		$this->addGoal(4, new TemptGoal());
		$this->addGoal(5, new FollowParentGoal());
		$this->addGoal(6, new LookAtPlayerGoal());
		$this->addGoal(7, new RandomStrollGoal());
		$this->registerExtraGoals();
	}

	protected function spawnParticleCloud(Particle $particle) : void{
		$world = $this->getWorld();
		$width = $this->size->getWidth();
		$height = $this->size->getHeight();
		for($i = 0; $i < 5; ++$i){
			$world->addParticle($this->location->add(
				(mt_rand(-100, 100) / 100) * $width,
				(mt_rand(30, 100) / 100) * $height,
				(mt_rand(-100, 100) / 100) * $width
			), $particle);
		}
	}

	/**
	 * Hook for species-specific goals (tempting, following parent...).
	 */
	protected function registerExtraGoals() : void{
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		if($this->isBreedingFood($item)){
			if($this->baby){
				//feeding a baby brings its adulthood 10% closer (vanilla), with green growth particles
				$this->babyGrowTicks = max(0, $this->babyGrowTicks - max(1, intdiv($this->babyGrowTicks, 10)));
				$this->consumeFeedItem($player, $item);
				$this->spawnParticleCloud(new HappyVillagerParticle());
				return true;
			}
			if($this->breedCooldownTicks <= 0 && !$this->isInLove()){
				$this->setInLove();
				$this->consumeFeedItem($player, $item);
				return true;
			}
		}
		return parent::onInteract($player, $clickPos);
	}

	protected function consumeFeedItem(Player $player, Item $item) : void{
		if($player->hasFiniteResources()){
			$item->pop();
			$player->getInventory()->setItemInHand($item);
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->closed || !$this->isAlive()){
			return $hasUpdate;
		}

		if($this->breedCooldownTicks > 0){
			$this->breedCooldownTicks -= $tickDiff;
		}

		if($this->baby){
			$this->babyGrowTicks -= $tickDiff;
			if($this->babyGrowTicks <= 0){
				$this->setBaby(false);
			}
		}elseif($this->inLoveTicks > 0){
			$this->inLoveTicks -= $tickDiff;
			if($this->inLoveTicks > 0 && ($this->ticksLived % 8) === 0){
				$this->tryBreed();
			}
		}

		return $hasUpdate;
	}

	private function tryBreed() : void{
		$world = $this->getWorld();
		//the BreedGoal walks the two mates together; breeding completes once they are adjacent
		foreach($world->getNearbyEntities($this->boundingBox->expandedCopy(3, 2, 3), $this) as $entity){
			if(
				$entity instanceof Animal &&
				$entity::class === static::class &&
				!$entity->isBaby() &&
				$entity->isInLove()
			){
				//only the lower-id partner spawns the baby so a pair produces exactly one child
				if($this->getId() < $entity->getId()){
					$this->breedWith($entity);
				}
				return;
			}
		}
	}

	private function breedWith(Animal $partner) : void{
		$this->inLoveTicks = 0;
		$partner->inLoveTicks = 0;
		$this->breedCooldownTicks = BreedingHelper::BREED_COOLDOWN_TICKS;
		$partner->breedCooldownTicks = BreedingHelper::BREED_COOLDOWN_TICKS;
		$this->networkPropertiesDirty = true;
		$partner->networkPropertiesDirty = true;

		$baby = $this->createChild();
		if($baby !== null){
			$baby->setBaby(true);
			$baby->spawnToAll();
		}
		//celebrate with hearts at both parents, like vanilla
		$this->spawnParticleCloud(new HeartParticle());
		$partner->spawnParticleCloud(new HeartParticle());
		$this->getWorld()->dropExperience($this->location, mt_rand(1, 7));
	}

	/**
	 * Creates the baby produced by breeding. Override for species whose offspring differ from the parent.
	 */
	protected function createChild() : ?Animal{
		return new static(Location::fromObject($this->location, $this->getWorld()));
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->baby);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_BABY, $this->baby ? 1 : 0);
		$nbt->setInt(self::TAG_IN_LOVE, $this->inLoveTicks);
		$nbt->setInt(self::TAG_BREED_COOLDOWN, $this->breedCooldownTicks);
		$nbt->setInt(self::TAG_BABY_GROW, $this->babyGrowTicks);
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->baby = $nbt->getByte(self::TAG_BABY, 0) !== 0;
		$this->inLoveTicks = $nbt->getInt(self::TAG_IN_LOVE, 0);
		$this->breedCooldownTicks = $nbt->getInt(self::TAG_BREED_COOLDOWN, 0);
		$this->babyGrowTicks = $nbt->getInt(self::TAG_BABY_GROW, $this->baby ? BreedingHelper::BABY_GROW_TICKS : 0);
		parent::initEntity($nbt);
		//the Entity constructor sized us as an adult before the baby flag was loaded; correct the hitbox now
		if($this->baby){
			$this->setSize($this->getInitialSizeInfo());
		}
	}
}
