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

namespace pocketmine\command;

use pocketmine\network\mcpe\protocol\types\command\CommandOverload;

/**
 * A {@link Command} may implement this to advertise typed parameter overloads (with enums) to the client, so the game
 * shows argument autocomplete instead of the generic free-text "args" parameter. {@link
 * \pocketmine\network\mcpe\NetworkSession::syncAvailableCommands} uses these overloads when present.
 */
interface CommandOverloadProvider{

	/**
	 * @return CommandOverload[] the command's parameter overloads (each a distinct argument signature)
	 */
	public function getCommandOverloads() : array;
}
