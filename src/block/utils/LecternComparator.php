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

namespace pocketmine\block\utils;

use function intval;

/**
 * Pure comparator-output maths for a lectern, isolated so it can be unit-tested. A comparator reading a lectern outputs a
 * signal proportional to how far through the book the reader has turned: 0 with no book, otherwise 1 on the first page up
 * to 15 on the last (matching vanilla {@code floor(page / (pages - 1) * 14) + 1}).
 */
final class LecternComparator{

	private function __construct(){
		//NOOP
	}

	public static function signalStrength(int $viewedPage, int $pageCount) : int{
		if($pageCount <= 0){
			return 0; //no book / no pages
		}
		$fraction = $pageCount > 1 ? $viewedPage / ($pageCount - 1) : 1.0;
		return intval($fraction * 14) + 1;
	}
}
