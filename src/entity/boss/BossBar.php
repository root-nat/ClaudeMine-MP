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

namespace pocketmine\entity\boss;

use pocketmine\network\mcpe\protocol\types\BossBarColor;
use function max;
use function min;

/**
 * The display state of a boss health bar: title, fill percentage (0..1, clamped), colour and screen-darkening. Pure
 * data model so the bar state can be unit-tested; broadcasting the state to players via BossEventPacket is handled
 * separately by the boss entity.
 */
final class BossBar{

	private float $percentage = 1.0;

	public function __construct(
		private string $title,
		private int $color = BossBarColor::PURPLE,
		private bool $darkenScreen = false
	){}

	public function getTitle() : string{
		return $this->title;
	}

	public function setTitle(string $title) : void{
		$this->title = $title;
	}

	public function getPercentage() : float{
		return $this->percentage;
	}

	public function setPercentage(float $percentage) : void{
		$this->percentage = max(0.0, min(1.0, $percentage));
	}

	/**
	 * Sets the bar fill from a current/maximum health pair.
	 */
	public function setHealth(float $current, float $max) : void{
		$this->setPercentage($max <= 0.0 ? 0.0 : $current / $max);
	}

	public function getColor() : int{
		return $this->color;
	}

	public function setColor(int $color) : void{
		$this->color = $color;
	}

	public function isDarkenScreen() : bool{
		return $this->darkenScreen;
	}

	public function setDarkenScreen(bool $darkenScreen) : void{
		$this->darkenScreen = $darkenScreen;
	}
}
