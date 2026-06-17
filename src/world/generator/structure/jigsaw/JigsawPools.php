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

/**
 * A registry of named {@link TemplatePool}s for one jigsaw structure type (e.g. a village's house/street/decoration
 * pools). The {@link JigsawAssembler} resolves a connector's pool name through this.
 */
final class JigsawPools{

	/** @var array<string, TemplatePool> */
	private array $pools = [];

	public function add(TemplatePool $pool) : void{
		$this->pools[$pool->getName()] = $pool;
	}

	public function get(string $name) : ?TemplatePool{
		return $this->pools[$name] ?? null;
	}
}
