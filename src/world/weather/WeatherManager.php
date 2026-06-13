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

namespace pocketmine\world\weather;

use pocketmine\event\world\WeatherChangeEvent;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\world\format\io\WorldData;
use pocketmine\world\gamerule\GameRule;
use pocketmine\world\World;
use function count;
use function mt_rand;

final class WeatherManager{
	public const NETWORK_MAX_INTENSITY = 65535;

	private bool $raining = false;
	private bool $thundering = false;
	private int $rainTime;
	private int $thunderTime;

	public function __construct(
		private World $world
	){
		$this->rainTime = self::pickClearRainDuration();
		$this->thunderTime = self::pickClearThunderDuration();
	}

	public static function pickRainDuration() : int{
		return mt_rand(12000, 23999);
	}

	public static function pickClearRainDuration() : int{
		return mt_rand(12000, 179999);
	}

	public static function pickThunderDuration() : int{
		return mt_rand(3600, 15599);
	}

	public static function pickClearThunderDuration() : int{
		return mt_rand(12000, 179999);
	}

	public function isRaining() : bool{
		return $this->raining;
	}

	public function isThundering() : bool{
		return $this->raining && $this->thundering;
	}

	public function getRainLevel() : float{
		return $this->raining ? 1.0 : 0.0;
	}

	public function getLightningLevel() : float{
		return $this->isThundering() ? 1.0 : 0.0;
	}

	public function getRainTime() : int{
		return $this->rainTime;
	}

	public function setRainTime(int $ticks) : void{
		$this->rainTime = $ticks;
	}

	public function getThunderTime() : int{
		return $this->thunderTime;
	}

	public function setThunderTime(int $ticks) : void{
		$this->thunderTime = $ticks;
	}

	public function readSaveData(WorldData $data) : void{
		$this->raining = $data->getRainLevel() > 0;
		$this->thundering = $data->getLightningLevel() > 0;
		$rainTime = $data->getRainTime();
		$thunderTime = $data->getLightningTime();
		$this->rainTime = $rainTime > 0 ? $rainTime : ($this->raining ? self::pickRainDuration() : self::pickClearRainDuration());
		$this->thunderTime = $thunderTime > 0 ? $thunderTime : ($this->thundering ? self::pickThunderDuration() : self::pickClearThunderDuration());
	}

	public function writeSaveData(WorldData $data) : void{
		$data->setRainLevel($this->getRainLevel());
		$data->setRainTime($this->rainTime);
		$data->setLightningLevel($this->thundering ? 1.0 : 0.0);
		$data->setLightningTime($this->thunderTime);
	}

	/**
	 * @internal
	 */
	public function tick() : void{
		if(!$this->world->getGameRules()->getBool(GameRule::DO_WEATHER_CYCLE)){
			return;
		}
		if(--$this->rainTime <= 0){
			$newRaining = !$this->raining;
			if(!$this->applyState($newRaining, $this->thundering, $newRaining ? self::pickRainDuration() : self::pickClearRainDuration(), $this->thunderTime)){
				$this->rainTime = $this->raining ? self::pickRainDuration() : self::pickClearRainDuration();
			}
		}
		if(--$this->thunderTime <= 0){
			$newThundering = !$this->thundering;
			if(!$this->applyState($this->raining, $newThundering, $this->rainTime, $newThundering ? self::pickThunderDuration() : self::pickClearThunderDuration())){
				$this->thunderTime = $this->thundering ? self::pickThunderDuration() : self::pickClearThunderDuration();
			}
		}
	}

	public function setRain(?int $duration = null) : bool{
		$duration ??= self::pickRainDuration();
		return $this->applyState(true, false, $duration, $this->thunderTime);
	}

	public function setThunder(?int $duration = null) : bool{
		$duration ??= self::pickThunderDuration();
		return $this->applyState(true, true, $duration, $duration);
	}

	public function setClear(?int $duration = null) : bool{
		return $this->applyState(false, false, $duration ?? self::pickClearRainDuration(), $duration ?? self::pickClearThunderDuration());
	}

	private function applyState(bool $raining, bool $thundering, int $rainTime, int $thunderTime) : bool{
		$ev = new WeatherChangeEvent($this->world, $raining, $raining && $thundering, $rainTime);
		$ev->call();
		if($ev->isCancelled()){
			return false;
		}

		$wasRaining = $this->raining;
		$wasThundering = $this->isThundering();

		$this->raining = $raining;
		$this->thundering = $thundering;
		$this->rainTime = $ev->getDuration();
		$this->thunderTime = $thunderTime;

		$this->broadcastChanges($wasRaining, $wasThundering);
		return true;
	}

	private function broadcastChanges(bool $wasRaining, bool $wasThundering) : void{
		$packets = [];
		if($this->raining !== $wasRaining){
			$packets[] = $this->raining ?
				LevelEventPacket::create(LevelEvent::START_RAIN, self::NETWORK_MAX_INTENSITY, null) :
				LevelEventPacket::create(LevelEvent::STOP_RAIN, 0, null);
		}
		if($this->isThundering() !== $wasThundering){
			$packets[] = $this->isThundering() ?
				LevelEventPacket::create(LevelEvent::START_THUNDER, self::NETWORK_MAX_INTENSITY, null) :
				LevelEventPacket::create(LevelEvent::STOP_THUNDER, 0, null);
		}
		if(count($packets) > 0){
			$players = $this->world->getPlayers();
			if(count($players) > 0){
				NetworkBroadcastUtils::broadcastPackets($players, $packets);
			}
		}
	}
}
