<?php
/**
 *	Logic for the Web Access Log Analyzer - CIDR processing.
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

class CIDR
{
	/*
	 * Properties
	 */
	public $prefix = '0.0.0.0';
	public $prefix_len = 32;
	public $prefix_dec = 0;
	public $prefix_packed = '';
	public $prefix_hex = '';

	public $min_ip = '0.0.0.0';
	public $min_dec = 0;
	public $min_packed = '';
	public $min_hex = '';

	public $max_ip = '0.0.0.0';
	public $max_dec = 0;
	public $max_packed = '';
	public $max_hex = '';

	public $ipv6 = false;
	public $valid = false;
	public $int_size = 32;

	/**
	 * Constructor
	 *
	 * Builds a CIDR object.  Input is a string, containing a CIDR or an IP address.
	 * No spaces within CIDR/IP string.  Will build valid objects for ipv4 or ipv6 CIDRs.
	 *
	 * @param string $cidr
	 * @return void
	 */
	function __construct($cidr = '0.0.0.0/32')
	{
		// [0] = whole, [1] = prefix, [2] = prefix length
		$matches = array();
		preg_match('~^\s*([^\/\s]{2,45})(\/.*)?~', $cidr, $matches);

		// No hit at all...
		if (empty($matches))
		{
		    return;
		}

		// Prefix must be a valid IP...
		if (filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		{
			$this->prefix = $matches[1];
			$this->ipv6 = false;
			$this->int_size = 32;
		}
		elseif (filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
		{
			$this->prefix = $matches[1];
			$this->ipv6 = true;
			$this->int_size = 128;
		}
		else
		{
			return;
		}

		// If provided, length must be valid per ipv4/ipv6.
		if (isset($matches[2]))
		{
			$pfx_match = array();
			preg_match('~\/(\d{1,3})\b~', $matches[2], $pfx_match);
			if (!empty($pfx_match) && (((int) $pfx_match[1]) >= 0) && (((int) $pfx_match[1]) <= $this->int_size))
			{
				$this->prefix_len =  (int) $pfx_match[1];
			}
			else
			{
				return;
			}
		}
		else
		{
			// if Length not provided, it is an IP, so use int_size...
			$this->prefix_len = $this->int_size;
		}

		// OK, you've run the gauntlet...
		$this->valid = true;

		$this->prefix_packed = inet_pton($this->prefix);
		$this->prefix_hex = bin2hex($this->prefix_packed);
		$this->prefix_dec = Packed::packeddec($this->prefix_packed, 2, 10);

		// Calc min & max
		$ip_mask = Packed::rmask($this->int_size - $this->prefix_len, $this->int_size);
		$this->max_packed = $this->prefix_packed | $ip_mask;
		$this->max_ip = inet_ntop($this->max_packed);
		$this->max_hex = bin2hex($this->max_packed);
		$this->max_dec = Packed::packeddec($this->max_packed);

		$ip_mask = ~$ip_mask;
		$this->min_packed = $this->prefix_packed & $ip_mask;
		$this->min_ip = inet_ntop($this->min_packed);
		$this->min_hex = bin2hex($this->min_packed);
		$this->min_dec = Packed::packeddec($this->min_packed);
	}

	public function to_text()
	{
	    return $this->prefix . '/' . $this->prefix_len;
	}

	// Quick check to see if an IP (passed in packed format) is within this CIDR
	public function contains($ip_packed)
	{
		return (($ip_packed >= $this->min_packed) && ($ip_packed <= $this->max_packed));
	}

	/**
	 * Calculate a CIDR (or set of CIDRs) for an IP range provided in packed format.
	 * Output is an array of CIDRs in string format (e.g., "77.88.0.0/16").  It must be an array
	 * because it may take more than one CIDR to describe the requested IP range.
	 *
	 * Algorithm informed by: https://blog.ip2location.com/knowledge-base/how-to-convert-ip-address-range-into-cidr/
	 * Great article, short & to the point, with examples.
	 */
	static function build($min, $max, $int_size)
	{
		// Need to whittle away at these...  Break one big range down to a number of
		// smaller ranges that can each be expressed in CIDR format.
		$cidrs = array();

		// Start at the min end of the range, build the biggest CIDR you can that's still smaller
		// than the WHOLE range, that starts with the curr min value, add to $cidrs[], increment min
		// to account for covering that entry, & repeat.
		while($max >= $min)
		{
			// $prefix = current subrange prefix; find the biggest prefix that works via
			// stepping thru left-justified masks.  ((Int_size - 0) bits on the right of $min...)
			$prefix = $int_size;
			while ($prefix > 0)
			{
				$mask = Packed::lmask($prefix - 1, $int_size);
				$mask_base = $min & $mask;
				if($mask_base != $min)
					break;
				$prefix--;
			}

			// $smallest_prefix = biggest power of 2 that stays within the remaining range... small prefix = big #...
			//  (= Count up to first 1 bit in binary representation of $diff...)
			// $diff = $max - $min + 1 = number of IPs in range
			$diff = Packed::subtract($max, $min);
			$diff = Packed::inc($diff);
			$smallest_prefix = 0;
			while ($smallest_prefix <= $int_size)
			{
				$mask = Packed::lmask($smallest_prefix, $int_size);
				$mask_base = $diff & $mask;
				if(!Packed::is_zero($mask_base))
					break;
				$smallest_prefix++;
			}
			// This can happen when testing extremes & need more bits than you got...
			if ($smallest_prefix > $int_size)
				$smallest_prefix = 0;
			// Prevent overshoot...
			if($prefix < $smallest_prefix)
				$prefix = $smallest_prefix;

			$cidrs[] = inet_ntop($min) . '/' . $prefix;

			$pow2 = Packed::pow2($int_size - $prefix, $int_size);
			$min = Packed::add($min, $pow2);

			// This can happen when testing extremes & need more bits than you got...
			// Face it, you're done already...
			if (($prefix === 0) || (Packed::is_zero($min)))
				break;
		}
		return $cidrs;
	}
}