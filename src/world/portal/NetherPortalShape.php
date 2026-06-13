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

namespace pocketmine\world\portal;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Axis;
use pocketmine\math\Vector3;
use pocketmine\world\Dimension;
use pocketmine\world\World;

final class NetherPortalShape{
	public const MIN_WIDTH = 2;
	public const MAX_WIDTH = 21;
	public const MIN_HEIGHT = 3;
	public const MAX_HEIGHT = 21;

	private function __construct(
		private World $world,
		private int $leftX,
		private int $bottomY,
		private int $leftZ,
		private int $axis,
		private int $width,
		private int $height
	){}

	public function getWidth() : int{
		return $this->width;
	}

	public function getHeight() : int{
		return $this->height;
	}

	public function getAxis() : int{
		return $this->axis;
	}

	public static function tryIgnite(World $world, Vector3 $pos) : bool{
		if($world->getDimension() === Dimension::THE_END){
			return false;
		}
		foreach([Axis::X, Axis::Z] as $axis){
			$shape = self::detect($world, $pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), $axis);
			if($shape !== null){
				$shape->fill();
				return true;
			}
		}
		return false;
	}

	public static function detect(World $world, int $x, int $y, int $z, int $axis) : ?self{
		$dx = $axis === Axis::X ? 1 : 0;
		$dz = $axis === Axis::Z ? 1 : 0;

		if(!self::isEmpty($world->getBlockAt($x, $y, $z))){
			return null;
		}

		while($y > $world->getMinY() + 1 && self::isEmpty($world->getBlockAt($x, $y - 1, $z))){
			--$y;
		}
		if(!self::isFrame($world->getBlockAt($x, $y - 1, $z))){
			return null;
		}

		$offset = 0;
		while($offset < self::MAX_WIDTH){
			$nextX = $x - $dx * ($offset + 1);
			$nextZ = $z - $dz * ($offset + 1);
			if(self::isEmpty($world->getBlockAt($nextX, $y, $nextZ)) && self::isFrame($world->getBlockAt($nextX, $y - 1, $nextZ))){
				++$offset;
			}else{
				break;
			}
		}
		$leftX = $x - $dx * $offset;
		$leftZ = $z - $dz * $offset;
		if(!self::isFrame($world->getBlockAt($leftX - $dx, $y, $leftZ - $dz))){
			return null;
		}

		$width = 0;
		while($width < self::MAX_WIDTH){
			$columnX = $leftX + $dx * $width;
			$columnZ = $leftZ + $dz * $width;
			if(self::isEmpty($world->getBlockAt($columnX, $y, $columnZ)) && self::isFrame($world->getBlockAt($columnX, $y - 1, $columnZ))){
				++$width;
			}else{
				break;
			}
		}
		if($width < self::MIN_WIDTH){
			return null;
		}
		if(!self::isFrame($world->getBlockAt($leftX + $dx * $width, $y, $leftZ + $dz * $width))){
			return null;
		}

		$height = 0;
		while($height < self::MAX_HEIGHT){
			$rowY = $y + $height;
			if(!self::isFrame($world->getBlockAt($leftX - $dx, $rowY, $leftZ - $dz)) || !self::isFrame($world->getBlockAt($leftX + $dx * $width, $rowY, $leftZ + $dz * $width))){
				break;
			}
			$rowEmpty = true;
			for($i = 0; $i < $width; ++$i){
				if(!self::isEmpty($world->getBlockAt($leftX + $dx * $i, $rowY, $leftZ + $dz * $i))){
					$rowEmpty = false;
					break;
				}
			}
			if(!$rowEmpty){
				break;
			}
			++$height;
		}
		if($height < self::MIN_HEIGHT){
			return null;
		}

		for($i = 0; $i < $width; ++$i){
			if(!self::isFrame($world->getBlockAt($leftX + $dx * $i, $y + $height, $leftZ + $dz * $i))){
				return null;
			}
		}

		return new self($world, $leftX, $y, $leftZ, $axis, $width, $height);
	}

	public function fill() : void{
		$dx = $this->axis === Axis::X ? 1 : 0;
		$dz = $this->axis === Axis::Z ? 1 : 0;
		$portal = VanillaBlocks::NETHER_PORTAL()->setAxis($this->axis);

		for($h = 0; $h < $this->height; ++$h){
			for($i = 0; $i < $this->width; ++$i){
				$this->world->setBlockAt($this->leftX + $dx * $i, $this->bottomY + $h, $this->leftZ + $dz * $i, $portal, false);
			}
		}
	}

	private static function isEmpty(Block $block) : bool{
		$typeId = $block->getTypeId();
		return $typeId === BlockTypeIds::AIR || $typeId === BlockTypeIds::FIRE || $typeId === BlockTypeIds::NETHER_PORTAL;
	}

	private static function isFrame(Block $block) : bool{
		return $block->getTypeId() === BlockTypeIds::OBSIDIAN;
	}
}
