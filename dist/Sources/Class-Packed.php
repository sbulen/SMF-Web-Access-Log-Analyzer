<?php
/**
 *	Logic for the Web Access Log Analyzer - CIDR processing, packed math.
 *
 *	Copyright 2025-2026 Shawn Bulen
 *
 *	The Web Access Log Analyzer is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	This software is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this software.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

/**
 * A bunch of static functions for dealing with packed values, such as output from inet_pton().
 */
class Packed
{
	// Generates packed mask based on specified length...
	// With $size number of rightmost bits set...
	static function rmask($size, $int_size)
	{
		$bytes = (int) $int_size / 8;
		$mask = '';
		for ($i = $bytes; $i > 0; $i--)
		{
			if ($size >= ($i * 8))
				$setbits = 8;
			elseif($size <= (($i - 1) * 8))
				$setbits = 0;
			else
				$setbits = ($size - (($i - 1) * 8));

			$mask .= chr((2 ** $setbits) - 1);
		}
		return $mask;
	}

	// Generates packed mask based on specified length...
	// With $size number of leftmost bits set...
	static function lmask($size, $int_size)
	{
		// Heck, leverage the other guy...
		$mask = Packed::rmask($int_size - $size, $int_size);
		$mask = ~$mask;
		return $mask;
	}

	// Translates packed to decimal.
	// Works for any size, but, for ipv6, may end up returning a float, not an int.
	// ***I.e., can't do math with this output...***
	static function packeddec($packed)
	{
		$len = strlen($packed);
		$dec = 0;
		for ($i = $len - 1; $i >= 0; $i--)
			$dec += ord(substr($packed, $i, 1))*(256**(($len - 1) - $i));
		return $dec;
	}

	// Translates int to packed.
	// ***Only works for values small enough to be represented by an int.***
	static function decpacked($dec, $int_size)
	{
		$fmt = ($int_size === 32) ? 'N' : 'J';
		$packed = pack($fmt, $dec);
		return $packed;
	}

	// Add two packed values.
	static function add($value1, $value2)
	{
		$bytes = strlen($value1);
		$new = '';
		$co = 0;
		for ($i = $bytes - 1; $i >= 0; $i--)
		{
			$temp = ord(substr($value1, $i, 1)) + ord(substr($value2, $i, 1)) + $co;
			if ($temp > 255)
			{
				$co = 1;
				$temp = $temp - 256;
			}
			else
			{
				$co = 0;
			}
			$new = chr($temp) . $new;
		}
		return $new;
	}

	// Subtract two packed values.
	static function subtract($value1, $value2)
	{
		$bytes = strlen($value1);
		$new = '';
		$bo = 0;
		for ($i = $bytes - 1; $i >= 0; $i--)
		{
			$temp = ord(substr($value1, $i, 1)) - ord(substr($value2, $i, 1)) - $bo;
			if ($temp < 0)
			{
				$bo = 1;
				$temp = $temp + 256;
			}
			else
			{
				$bo = 0;
			}
			$new = chr($temp) . $new;
		}
		return $new;
	}

	// Check if zero.
	static function is_zero($packed)
	{
		$bytes = strlen($packed);
		$zero = true;
		for ($i = $bytes - 1; $i >= 0; $i--)
		{
			if (ord(substr($packed, $i, 1)) !== 0)
			{
				$zero = false;
				break;
			}
		}
		return $zero;
	}

	// Add one.
	static function inc($packed)
	{
		$bytes = strlen($packed);
		$new = '';
		$co = 0;
		for ($i = $bytes - 1; $i >= 0; $i--)
		{
			$temp = ord(substr($packed, $i, 1)) + $co;
			if ($i === ($bytes - 1))
				$temp++;
			if ($temp > 255)
			{
				$co = 1;
				$temp = 0;
			}
			else
			{
				$co = 0;
			}
			$new = chr($temp) . $new;
		}
		return $new;
	}

	// Create a packed value for the given power of 2...
	static function pow2($value, $int_size)
	{
		$bytes = (int) ($int_size / 8);
		$value = (int) $value;
		$new = '';
		for ($i = $bytes; $i > 0; $i--)
		{
			if (($value < 8) && ($value >= 0))
				$temp = 2 ** $value;
			else
				$temp = 0;
			$new = chr($temp) . $new;
			$value = (int) ($value - 8);
		}
		return $new;
	}
}
