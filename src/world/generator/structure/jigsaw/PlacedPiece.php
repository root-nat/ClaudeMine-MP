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
 * A {@link StructureTemplate} (already position-rotated) committed at an absolute origin within an assembled structure.
 * It exposes its world bounding box, its connectors in world coordinates (for the assembler to expand from), its blocks
 * in world coordinates (for the renderer/populator to write, clipped per chunk) and its furnishing markers in world
 * coordinates (for the main-thread furnisher). $rotation records how many quarter-turns the template was rotated, so the
 * writer can rotate the block-states' FACING by the same amount (positions are already rotated; facings are not).
 */
final class PlacedPiece{

	public function __construct(
		public readonly StructureTemplate $template,
		public readonly int $originX,
		public readonly int $originY,
		public readonly int $originZ,
		public readonly int $rotation = 0
	){}

	public function bounds() : StructureBoundingBox{
		return new StructureBoundingBox(
			$this->originX,
			$this->originY,
			$this->originZ,
			$this->originX + $this->template->getSizeX() - 1,
			$this->originY + $this->template->getSizeY() - 1,
			$this->originZ + $this->template->getSizeZ() - 1
		);
	}

	/**
	 * The piece's connectors translated to world coordinates.
	 *
	 * @return JigsawConnector[]
	 */
	public function worldConnectors() : array{
		$out = [];
		foreach($this->template->getConnectors() as $c){
			$out[] = new JigsawConnector($this->originX + $c->x, $this->originY + $c->y, $this->originZ + $c->z, $c->facing, $c->pool);
		}
		return $out;
	}

	/**
	 * The piece's blocks translated to world coordinates: [x, y, z, block state id]. The state id is still in the
	 * template's original orientation - the writer must rotate its FACING by {@link self::$rotation} (see
	 * {@link BlockStateRotator}).
	 *
	 * @return list<array{int, int, int, int}>
	 */
	public function worldBlocks() : array{
		$out = [];
		foreach($this->template->getBlocks() as [$x, $y, $z, $stateId]){
			$out[] = [$this->originX + $x, $this->originY + $y, $this->originZ + $z, $stateId];
		}
		return $out;
	}

	/**
	 * The piece's furnishing markers translated to world coordinates: [x, y, z, type].
	 *
	 * @return list<array{int, int, int, string}>
	 */
	public function worldMarkers() : array{
		$out = [];
		foreach($this->template->getMarkers() as [$x, $y, $z, $type]){
			$out[] = [$this->originX + $x, $this->originY + $y, $this->originZ + $z, $type];
		}
		return $out;
	}
}
