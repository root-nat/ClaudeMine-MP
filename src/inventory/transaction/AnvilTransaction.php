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

namespace pocketmine\inventory\transaction;

use pocketmine\block\utils\AnvilHelper;
use pocketmine\item\Item;
use pocketmine\player\Player;
use function count;

/**
 * Validates and applies an anvil operation: the inputs (base item, and optionally a material) are consumed, the combined
 * result is produced, and the player is charged the experience-level cost. The expected result and cost are precomputed
 * by {@link AnvilHelper} when the player takes the output.
 */
class AnvilTransaction extends InventoryTransaction{

	public function __construct(
		Player $source,
		private readonly Item $expectedResult,
		private readonly int $cost
	){
		parent::__construct($source);
	}

	public function validate() : void{
		if(count($this->actions) < 1){
			throw new TransactionValidationException("Transaction must have at least one action to be executable");
		}

		/** @var Item[] $outputs */
		$outputs = [];
		/** @var Item[] $inputs */
		$inputs = [];
		$this->matchItems($outputs, $inputs);

		if(count($outputs) !== 1){
			throw new TransactionValidationException("Expected exactly 1 output item from the anvil, got " . count($outputs));
		}
		if(!$outputs[0]->equalsExact($this->expectedResult)){
			throw new TransactionValidationException("Anvil output does not match the expected combined item");
		}

		$inputCount = count($inputs);
		if($inputCount < 1 || $inputCount > 2){
			throw new TransactionValidationException("Expected 1 or 2 input items for the anvil, got $inputCount");
		}

		if($this->source->hasFiniteResources()){
			if($this->cost >= AnvilHelper::TOO_EXPENSIVE_COST){
				throw new TransactionValidationException("Anvil operation is too expensive ($this->cost levels)");
			}
			$xpLevel = $this->source->getXpManager()->getXpLevel();
			if($xpLevel < $this->cost){
				throw new TransactionValidationException("Player's XP level $xpLevel is less than the anvil cost $this->cost");
			}
		}
	}

	public function execute() : void{
		parent::execute();

		if($this->source->hasFiniteResources()){
			$this->source->getXpManager()->subtractXpLevels($this->cost);
		}
	}
}
