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

namespace pocketmine\command\defaults;

use pocketmine\command\CommandOverloadProvider;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\network\mcpe\protocol\types\command\CommandHardEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandOverload;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\Random;
use pocketmine\utils\TextFormat;
use pocketmine\world\generator\normal\OverworldBiomeSelector;
use pocketmine\world\generator\structure\AmethystGeodeStructure;
use pocketmine\world\generator\structure\BuriedTreasureStructure;
use pocketmine\world\generator\structure\DesertTempleStructure;
use pocketmine\world\generator\structure\DesertWellStructure;
use pocketmine\world\generator\structure\FossilStructure;
use pocketmine\world\generator\structure\IglooStructure;
use pocketmine\world\generator\structure\JungleTempleStructure;
use pocketmine\world\generator\structure\OceanRuinsStructure;
use pocketmine\world\generator\structure\OverworldLocator;
use pocketmine\world\generator\structure\PillagerOutpostStructure;
use pocketmine\world\generator\structure\RuinedPortalStructure;
use pocketmine\world\generator\structure\ShipwreckStructure;
use pocketmine\world\generator\structure\VillageStructure;
use pocketmine\world\generator\structure\WitchHutStructure;
use function array_keys;
use function array_merge;
use function count;
use function floor;
use function implode;
use function strtolower;

/**
 * /locate <structure|biome> <type> - finds the nearest overworld structure or biome, vanilla-style. Operator-only.
 *
 * It works WITHOUT the area being generated: structures are re-derived from the same seed-based anchor draw the
 * generator uses, and biomes from a seed-rebuilt {@link OverworldBiomeSelector} - so the reported position is exactly
 * where the world will generate that structure/biome (see {@link OverworldLocator}).
 */
class LocateCommand extends VanillaCommand implements CommandOverloadProvider{

	private const STRUCTURE_MAX_CHUNKS = 128;
	private const BIOME_MAX_CHUNKS = 80;

	/** @var array<string, array{int, int, list<int>}> name => [salt, rarity, allowed biome ids] */
	private const STRUCTURES = [
		"desert_temple" => [DesertTempleStructure::SALT, DesertTempleStructure::RARITY, [BiomeIds::DESERT]],
		"desert_well" => [DesertWellStructure::SALT, DesertWellStructure::RARITY, [BiomeIds::DESERT]],
		"fossil" => [FossilStructure::SALT, FossilStructure::RARITY, [BiomeIds::DESERT, BiomeIds::SWAMPLAND]],
		"witch_hut" => [WitchHutStructure::SALT, WitchHutStructure::RARITY, [BiomeIds::SWAMPLAND]],
		"jungle_temple" => [JungleTempleStructure::SALT, JungleTempleStructure::RARITY, [BiomeIds::JUNGLE]],
		"pillager_outpost" => [PillagerOutpostStructure::SALT, PillagerOutpostStructure::RARITY, [BiomeIds::PLAINS, BiomeIds::DESERT, BiomeIds::TAIGA]],
		"igloo" => [IglooStructure::SALT, IglooStructure::RARITY, [BiomeIds::ICE_PLAINS, BiomeIds::COLD_TAIGA]],
		"ruined_portal" => [RuinedPortalStructure::SALT, RuinedPortalStructure::RARITY, []],
		"amethyst_geode" => [AmethystGeodeStructure::SALT, AmethystGeodeStructure::RARITY, []],
		"shipwreck" => [ShipwreckStructure::SALT, ShipwreckStructure::RARITY, self::OCEANS],
		"buried_treasure" => [BuriedTreasureStructure::SALT, BuriedTreasureStructure::RARITY, [...self::OCEANS, BiomeIds::BEACH]],
		"ocean_ruins" => [OceanRuinsStructure::SALT, OceanRuinsStructure::RARITY, self::OCEANS],
		"village" => [VillageStructure::SALT, VillageStructure::RARITY, [BiomeIds::PLAINS, BiomeIds::SAVANNA, BiomeIds::TAIGA, BiomeIds::DESERT, BiomeIds::ICE_PLAINS, BiomeIds::COLD_TAIGA]]
	];

	/** @var list<int> all ocean biome ids (where ocean structures may anchor) */
	private const OCEANS = [BiomeIds::OCEAN, BiomeIds::DEEP_OCEAN, BiomeIds::WARM_OCEAN, BiomeIds::LUKEWARM_OCEAN, BiomeIds::COLD_OCEAN, BiomeIds::FROZEN_OCEAN];

	/** @var array<string, string> alias => canonical structure name */
	private const STRUCTURE_ALIASES = [
		"desert_pyramid" => "desert_temple",
		"pyramid" => "desert_temple",
		"temple" => "desert_temple",
		"well" => "desert_well",
		"swamp_hut" => "witch_hut",
		"jungle_pyramid" => "jungle_temple",
		"outpost" => "pillager_outpost",
		"portal" => "ruined_portal",
		"geode" => "amethyst_geode",
		"wreck" => "shipwreck",
		"treasure" => "buried_treasure",
		"ruins" => "ocean_ruins",
		"villages" => "village",
		"town" => "village"
	];

	/** @var array<string, int> biome name => biome id */
	private const BIOMES = [
		"ocean" => BiomeIds::OCEAN,
		"plains" => BiomeIds::PLAINS,
		"desert" => BiomeIds::DESERT,
		"mountains" => BiomeIds::EXTREME_HILLS,
		"extreme_hills" => BiomeIds::EXTREME_HILLS,
		"forest" => BiomeIds::FOREST,
		"birch_forest" => BiomeIds::BIRCH_FOREST,
		"taiga" => BiomeIds::TAIGA,
		"swamp" => BiomeIds::SWAMPLAND,
		"river" => BiomeIds::RIVER,
		"ice_plains" => BiomeIds::ICE_PLAINS,
		"snowy" => BiomeIds::ICE_PLAINS,
		"jungle" => BiomeIds::JUNGLE,
		"savanna" => BiomeIds::SAVANNA,
		"mesa" => BiomeIds::MESA,
		"badlands" => BiomeIds::MESA
	];

	public function __construct(){
		parent::__construct(
			"locate",
			"Locate the nearest generated structure or biome",
			"/locate <structure|biome> <type>"
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_LOCATE);
	}

	/**
	 * @param string[] $args
	 */
	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) < 2){
			throw new InvalidCommandSyntaxException();
		}

		if($sender instanceof Player){
			$world = $sender->getWorld();
			$position = $sender->getPosition();
		}else{
			$world = $sender->getServer()->getWorldManager()->getDefaultWorld();
			if($world === null){
				$sender->sendMessage(TextFormat::RED . "Aucun monde cible.");
				return true;
			}
			$position = $world->getSpawnLocation();
		}

		$generator = strtolower($world->getProvider()->getWorldData()->getGenerator());
		if($generator !== "normal" && $generator !== "default"){
			$sender->sendMessage(TextFormat::RED . "/locate ne fonctionne que dans un monde Overworld (générateur normal).");
			return true;
		}

		$seed = $world->getSeed();
		$originX = (int) floor($position->x);
		$originZ = (int) floor($position->z);

		//rebuild the generator's biome selector from the world seed so biomes resolve identically without loading chunks
		$selector = new OverworldBiomeSelector(new Random($seed));
		$selector->recalculate();
		$biomeIdAt = static fn(int $x, int $z) : int => $selector->pickBiomeJittered($x, $z, $seed)->getId();

		$category = strtolower($args[0]);
		$type = strtolower($args[1]);

		if($category === "structure"){
			$key = self::STRUCTURE_ALIASES[$type] ?? $type;
			if(!isset(self::STRUCTURES[$key])){
				$sender->sendMessage(TextFormat::RED . "Structure inconnue. Disponibles : " . implode(", ", array_keys(self::STRUCTURES)));
				return true;
			}
			[$salt, $rarity, $biomeIds] = self::STRUCTURES[$key];
			$found = OverworldLocator::nearestStructure($seed, $salt, $rarity, $biomeIds, $originX, $originZ, self::STRUCTURE_MAX_CHUNKS, $biomeIdAt);
			$this->report($sender, $found, $key, self::STRUCTURE_MAX_CHUNKS);
			return true;
		}

		if($category === "biome"){
			if(!isset(self::BIOMES[$type])){
				$sender->sendMessage(TextFormat::RED . "Biome inconnu. Disponibles : " . implode(", ", array_keys(self::BIOMES)));
				return true;
			}
			$found = OverworldLocator::nearestBiome(self::BIOMES[$type], $originX, $originZ, self::BIOME_MAX_CHUNKS, $biomeIdAt);
			$this->report($sender, $found, $type, self::BIOME_MAX_CHUNKS);
			return true;
		}

		throw new InvalidCommandSyntaxException();
	}

	/**
	 * @param array{int, int, int}|null $found [x, z, distance] or null
	 */
	private function report(CommandSender $sender, ?array $found, string $name, int $maxChunks) : void{
		if($found === null){
			$sender->sendMessage(TextFormat::RED . "Aucun '$name' trouvé dans un rayon de " . ($maxChunks * 16) . " blocs.");
			return;
		}
		[$x, $z, $distance] = $found;
		$sender->sendMessage(TextFormat::GREEN . "Le '$name' le plus proche est à " . TextFormat::WHITE . "[$x, ~, $z]" . TextFormat::GREEN . " ($distance blocs).");
	}

	/**
	 * Two typed overloads so the client autocompletes "structure <name>" and "biome <name>" from the known catalogues.
	 *
	 * @return CommandOverload[]
	 */
	public function getCommandOverloads() : array{
		$structureNames = array_merge(array_keys(self::STRUCTURES), array_keys(self::STRUCTURE_ALIASES));
		$biomeNames = array_keys(self::BIOMES);
		return [
			new CommandOverload(false, [
				CommandParameter::enum("category", new CommandHardEnum("LocateStructureCategory", ["structure"]), 0),
				CommandParameter::enum("structure", new CommandHardEnum("LocateStructure", $structureNames), 0)
			]),
			new CommandOverload(false, [
				CommandParameter::enum("category", new CommandHardEnum("LocateBiomeCategory", ["biome"]), 0),
				CommandParameter::enum("biome", new CommandHardEnum("LocateBiome", $biomeNames), 0)
			])
		];
	}
}
