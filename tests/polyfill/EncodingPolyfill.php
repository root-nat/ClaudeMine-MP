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

namespace pmmp\encoding{

	use function pack;
	use function unpack;

	if(!\class_exists(BE::class, false) && !\extension_loaded("encoding")){
		final class BE{
			public static function packSignedLong(int $value) : string{
				return pack("J", $value);
			}

			public static function unpackSignedLong(string $bytes) : int{
				$result = unpack("J", $bytes);
				if($result === false){
					throw new \InvalidArgumentException("Not enough bytes to unpack a signed long");
				}
				return (int) $result[1];
			}

			public static function packUnsignedLong(int $value) : string{
				return pack("J", $value);
			}

			public static function unpackUnsignedLong(string $bytes) : int{
				return self::unpackSignedLong($bytes);
			}
		}

		final class LE{
			public static function packSignedLong(int $value) : string{
				return pack("P", $value);
			}

			public static function unpackSignedLong(string $bytes) : int{
				$result = unpack("P", $bytes);
				if($result === false){
					throw new \InvalidArgumentException("Not enough bytes to unpack a signed long");
				}
				return (int) $result[1];
			}

			public static function packUnsignedLong(int $value) : string{
				return pack("P", $value);
			}

			public static function unpackUnsignedLong(string $bytes) : int{
				return self::unpackSignedLong($bytes);
			}
		}
	}
}

namespace{

	if(!\function_exists("morton2d_encode")){
		function morton2d_part1by1(int $value) : int{
			$value &= 0xFFFFFFFF;
			$value = ($value | ($value << 16)) & 0x0000FFFF0000FFFF;
			$value = ($value | ($value << 8)) & 0x00FF00FF00FF00FF;
			$value = ($value | ($value << 4)) & 0x0F0F0F0F0F0F0F0F;
			$value = ($value | ($value << 2)) & 0x3333333333333333;
			return ($value | ($value << 1)) & 0x5555555555555555;
		}

		function morton2d_compact1by1(int $value) : int{
			$value &= 0x5555555555555555;
			$value = ($value | ($value >> 1)) & 0x3333333333333333;
			$value = ($value | ($value >> 2)) & 0x0F0F0F0F0F0F0F0F;
			$value = ($value | ($value >> 4)) & 0x00FF00FF00FF00FF;
			$value = ($value | ($value >> 8)) & 0x0000FFFF0000FFFF;
			return ($value | ($value >> 16)) & 0xFFFFFFFF;
		}

		function morton2d_encode(int $x, int $y) : int{
			return morton2d_part1by1($x) | (morton2d_part1by1($y) << 1);
		}

		/**
		 * @return int[]
		 * @phpstan-return array{int, int}
		 */
		function morton2d_decode(int $morton) : array{
			return [morton2d_compact1by1($morton), morton2d_compact1by1($morton >> 1)];
		}

		function morton3d_part1by2(int $value) : int{
			$value &= 0x1FFFFF;
			$value = ($value | ($value << 32)) & 0x001F00000000FFFF;
			$value = ($value | ($value << 16)) & 0x001F0000FF0000FF;
			$value = ($value | ($value << 8)) & 0x100F00F00F00F00F;
			$value = ($value | ($value << 4)) & 0x10C30C30C30C30C3;
			return ($value | ($value << 2)) & 0x1249249249249249;
		}

		function morton3d_compact1by2(int $value) : int{
			$value &= 0x1249249249249249;
			$value = ($value | ($value >> 2)) & 0x10C30C30C30C30C3;
			$value = ($value | ($value >> 4)) & 0x100F00F00F00F00F;
			$value = ($value | ($value >> 8)) & 0x001F0000FF0000FF;
			$value = ($value | ($value >> 16)) & 0x001F00000000FFFF;
			return ($value | ($value >> 32)) & 0x1FFFFF;
		}

		function morton3d_encode(int $x, int $y, int $z) : int{
			return morton3d_part1by2($x) | (morton3d_part1by2($y) << 1) | (morton3d_part1by2($z) << 2);
		}

		/**
		 * @return int[]
		 * @phpstan-return array{int, int, int}
		 */
		function morton3d_decode(int $morton) : array{
			return [morton3d_compact1by2($morton), morton3d_compact1by2($morton >> 1), morton3d_compact1by2($morton >> 2)];
		}
	}
}
