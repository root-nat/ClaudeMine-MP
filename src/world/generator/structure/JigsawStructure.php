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

namespace pocketmine\world\generator\structure;

use pocketmine\utils\Random;
use pocketmine\world\generator\carver\GenerationVolume;
use pocketmine\world\generator\structure\jigsaw\BlockStateRotator;
use pocketmine\world\generator\structure\jigsaw\JigsawAssembler;
use pocketmine\world\generator\structure\jigsaw\JigsawPools;
use pocketmine\world\generator\structure\jigsaw\PlacedPiece;
use pocketmine\world\generator\structure\jigsaw\StructureBoundingBox;
use pocketmine\world\generator\structure\jigsaw\StructureTemplate;

/**
 * A multi-piece structure assembled from jigsaw {@link StructureTemplate}s (villages, and later strongholds/mansions).
 * It anchors like any other {@link SurfaceStructurePopulator} structure - the assembly origin (template-local 0,0,0) is
 * the surface anchor - then writes every assembled piece's blocks clipped to the volume, rotating each block-state's
 * FACING to match the piece's rotation, and underpins each piece's floor with a few foundation blocks so it does not
 * float over minor dips.
 *
 * DETERMINISM: {@link self::assemblePieces} is a pure function of the passed {@link Random} (the only entropy is the
 * per-connector pool draw, in fixed BFS order), so every chunk that re-derives the same region anchor produces the same
 * layout and writes only its own clipped slice - no persisted structure-start cache. The SAME method is re-run by the
 * main-thread furnisher (with the same seeded Random) to recover marker positions, so loot/mobs land exactly in the
 * pieces the generator placed.
 *
 * ANCHOR STABILITY: the start template must keep its anchor column (local x=0, z=0) clear above the floor and solid at
 * the floor (so {@link SurfaceScan::topSolidY} returns the same ground Y from every chunk and from the furnisher).
 */
class JigsawStructure extends Structure{

	public function __construct(
		private StructureTemplate $start,
		private JigsawPools $pools,
		private int $maxPieces,
		private int $halfExtent,
		private int $maxHeight,
		private int $foundationDepth,
		private int $foundationStateId
	){}

	/**
	 * Assembles the structure's pieces around the assembly origin (0,0,0). Pure for a given Random; shared by
	 * {@link self::place} and the main-thread furnisher so both agree on every piece's position and rotation.
	 *
	 * @return PlacedPiece[]
	 */
	public function assemblePieces(Random $random) : array{
		$bounds = new StructureBoundingBox(-$this->halfExtent, 0, -$this->halfExtent, $this->halfExtent, $this->maxHeight, $this->halfExtent);
		return JigsawAssembler::assemble($this->start, $this->pools, $random, $this->maxPieces, $bounds);
	}

	public function canPlace(GenerationVolume $volume, int $x, int $y, int $z) : bool{
		return $volume->isInBounds($x, $y, $z)
			&& ($y + $this->maxHeight) < $volume->getMaxY()
			&& ($y - $this->foundationDepth) >= $volume->getMinY();
	}

	public function place(GenerationVolume $volume, int $x, int $y, int $z, Random $random) : void{
		foreach($this->assemblePieces($random) as $piece){
			$rotation = $piece->rotation;
			foreach($piece->worldBlocks() as [$lx, $ly, $lz, $stateId]){
				$wx = $x + $lx;
				$wy = $y + $ly;
				$wz = $z + $lz;
				if($volume->isInBounds($wx, $wy, $wz)){
					$volume->setBlockStateId($wx, $wy, $wz, BlockStateRotator::rotateY($stateId, $rotation));
				}
			}
			$this->underpin($volume, $piece, $x, $y, $z);
		}
	}

	/**
	 * Fills foundation blocks under a piece's lowest (floor) layer so it sits on the ground instead of floating. Pure: it
	 * reads only the piece's own cells, never the volume, so it is deterministic across chunk passes.
	 */
	private function underpin(GenerationVolume $volume, PlacedPiece $piece, int $x, int $y, int $z) : void{
		$blocks = $piece->worldBlocks();
		$minLy = null;
		foreach($blocks as $cell){
			if($minLy === null || $cell[1] < $minLy){
				$minLy = $cell[1];
			}
		}
		if($minLy === null){
			return;
		}
		foreach($blocks as [$lx, $ly, $lz]){
			if($ly !== $minLy){
				continue;
			}
			for($d = 1; $d <= $this->foundationDepth; ++$d){
				$wy = $y + $minLy - $d;
				if($volume->isInBounds($x + $lx, $wy, $z + $lz)){
					$volume->setBlockStateId($x + $lx, $wy, $z + $lz, $this->foundationStateId);
				}
			}
		}
	}
}
