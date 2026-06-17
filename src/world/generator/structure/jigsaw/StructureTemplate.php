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
 * A baked jigsaw piece: a bounded grid of block-state ids (sparse - only the cells the piece actually sets) plus the
 * named jigsaw connectors on its faces and optional furnishing markers (e.g. a chest or a villager spawn point that the
 * main-thread furnisher fills once the piece is placed). Supports clockwise Y rotation in 90-degree steps, used by the
 * {@link JigsawAssembler} to mate pieces; block positions, connectors AND markers all rotate together. Block-state
 * FACING is rotated separately at write time by {@link BlockStateRotator} (this class only moves cells), so the geometry
 * here stays a pure integer transform that is trivially unit-testable.
 */
final class StructureTemplate{

	/**
	 * @param list<array{int, int, int, int}>    $blocks     local [x, y, z, block state id] cells (only non-empty cells)
	 * @param JigsawConnector[]                  $connectors
	 * @param list<array{int, int, int, string}> $markers    local [x, y, z, type] furnishing markers (chest, villager, ...)
	 */
	public function __construct(
		private string $name,
		private int $sizeX,
		private int $sizeY,
		private int $sizeZ,
		private array $blocks,
		private array $connectors,
		private array $markers = []
	){}

	public function getName() : string{ return $this->name; }

	public function getSizeX() : int{ return $this->sizeX; }

	public function getSizeY() : int{ return $this->sizeY; }

	public function getSizeZ() : int{ return $this->sizeZ; }

	/** @return list<array{int, int, int, int}> */
	public function getBlocks() : array{ return $this->blocks; }

	/** @return JigsawConnector[] */
	public function getConnectors() : array{ return $this->connectors; }

	/** @return list<array{int, int, int, string}> */
	public function getMarkers() : array{ return $this->markers; }

	/**
	 * Returns a copy rotated $rotation quarter-turns clockwise about Y. Block positions, connectors and markers rotate
	 * together; for odd rotations the X and Z dimensions swap.
	 */
	public function rotated(int $rotation) : self{
		$rotation &= 3;
		if($rotation === 0){
			return $this;
		}
		$newSizeX = ($rotation === 1 || $rotation === 3) ? $this->sizeZ : $this->sizeX;
		$newSizeZ = ($rotation === 1 || $rotation === 3) ? $this->sizeX : $this->sizeZ;

		$blocks = [];
		foreach($this->blocks as [$x, $y, $z, $stateId]){
			[$rx, $rz] = self::rotateXZ($x, $z, $rotation, $this->sizeX, $this->sizeZ);
			$blocks[] = [$rx, $y, $rz, $stateId];
		}
		$connectors = [];
		foreach($this->connectors as $connector){
			$connectors[] = $connector->rotated($rotation, $this->sizeX, $this->sizeZ);
		}
		$markers = [];
		foreach($this->markers as [$x, $y, $z, $type]){
			[$rx, $rz] = self::rotateXZ($x, $z, $rotation, $this->sizeX, $this->sizeZ);
			$markers[] = [$rx, $y, $rz, $type];
		}
		return new self($this->name, $newSizeX, $this->sizeY, $newSizeZ, $blocks, $connectors, $markers);
	}

	/**
	 * Rotates a local (x, z) by $rotation quarter-turns clockwise about Y within the given pre-rotation footprint.
	 *
	 * @return array{int, int} [rotatedX, rotatedZ]
	 */
	public static function rotateXZ(int $x, int $z, int $rotation, int $sizeX, int $sizeZ) : array{
		for($i = 0, $r = $rotation & 3; $i < $r; ++$i){
			[$x, $z, $sizeX, $sizeZ] = [$sizeZ - 1 - $z, $x, $sizeZ, $sizeX];
		}
		return [$x, $z];
	}
}
