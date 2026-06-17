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

namespace pocketmine\world\generator\structure\jigsaw;

use pocketmine\utils\Random;
use function count;

/**
 * A named pool of interchangeable jigsaw pieces (e.g. "village/houses"). A connector pointing at this pool draws one of
 * its templates at random.
 */
final class TemplatePool{

	/**
	 * @param StructureTemplate[] $templates
	 */
	public function __construct(
		private string $name,
		private array $templates
	){}

	public function getName() : string{ return $this->name; }

	public function isEmpty() : bool{ return count($this->templates) === 0; }

	/**
	 * Picks a template at random (deterministic for a given Random), or null if the pool is empty.
	 */
	public function pick(Random $random) : ?StructureTemplate{
		$count = count($this->templates);
		if($count === 0){
			return null;
		}
		return $this->templates[$random->nextBoundedInt($count)];
	}
}
