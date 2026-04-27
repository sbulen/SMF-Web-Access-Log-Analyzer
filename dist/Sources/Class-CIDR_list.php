<?php
/**
 *	Logic for the Web Access Log Analyzer - CIDR List processing.
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

class CIDR_List
{
	/*
	 * Properties
	 */
	public $cidrs_ipv4 = array();
	public $cidrs_ipv6 = array();
	public $command = '';

	/**
	 * Constructor
	 *
	 * Builds a CIDR_list object given a raw input file.
	 * Input file must ONLY contain a list of CIDRs, one per line.
	 *
	 * @param string $file = filename of input file
	 * @param string $command = allows you to prepend a command to lines of output produced, e.g., 'Deny from'
	 * @return void
	 */
	function __construct($file, $command = '')
	{
		// Save off command, & ensure it ends in a space
		if (!empty($command) && is_string($command))
			$this->command = trim($command) . ' ';

		$fp = fopen($file, 'r');

		// Load the file
		$buffer = fgets($fp);
		while ($buffer !== false)
		{
			$buffer = trim($buffer);
			$temp_cidr = new CIDR($buffer);
			// Don't keep items that didn't convert OK (comments, etc...)
			if ($temp_cidr->valid)
			{
				// ipv6
				if ($temp_cidr->ipv6)
				{
					// Get rid of dupes & overlaps, with same starting min-value...
					if (key_exists($temp_cidr->min_packed, $this->cidrs_ipv6))
					{
						// If same starting value, keep the one with the lower prefix (wider range)...
						if ($this->cidrs_ipv6[$temp_cidr->min_packed]->prefix_len > $temp_cidr->prefix_len)
							$this->cidrs_ipv6[$temp_cidr->min_packed] = $temp_cidr;
					}
					else
						$this->cidrs_ipv6[$temp_cidr->min_packed] = $temp_cidr;
				}
				// ipv4
				else
				{
					// Get rid of dupes & overlaps, with same starting min-value...
					if (key_exists($temp_cidr->min_packed, $this->cidrs_ipv4))
					{
						// If same starting value, keep the one with the lower prefix (wider range)...
						if ($this->cidrs_ipv4[$temp_cidr->min_packed]->prefix_len > $temp_cidr->prefix_len)
							$this->cidrs_ipv4[$temp_cidr->min_packed] = $temp_cidr;
					}
					else
						$this->cidrs_ipv4[$temp_cidr->min_packed] = $temp_cidr;
				}
			}
			$buffer = fgets($fp);
		}
		fclose($fp);

		// Subsequent cleaning needs these sorted...
		ksort($this->cidrs_ipv4);
		ksort($this->cidrs_ipv6);

		// Loop thru & delete items that are already included in previous item...
		$this->remove_subsets($this->cidrs_ipv4);
		$this->remove_subsets($this->cidrs_ipv6);

		// Combine consecutive CIDRs...
		$this->cidrs_ipv4 = $this->combine_consecutive($this->cidrs_ipv4);
		$this->cidrs_ipv6 = $this->combine_consecutive($this->cidrs_ipv6);
	}

	// Remove subsets
	public function remove_subsets(&$cidrs)
	{
		$first_entry = true;
		foreach ($cidrs AS $entry)
		{
			if ((!$first_entry) && ($prev_cidr->contains($entry->min_packed) && $prev_cidr->contains($entry->max_packed)))
				unset($cidrs[$entry->min_packed]);
			else
			{
				$prev_cidr = $entry;
				$first_entry = false;
			}
		}
	}

	// Combine where possible
	public function combine_consecutive(&$cidrs)
	{
		// Note they don't always combine into one, sometimes 15 CIDRs combine into 3...
		$curr_range_start = '';
		$curr_range_end = '';
		$cleansed_cidrs = array();
		$first_entry = true;
		foreach ($cidrs AS $entry)
		{
			if ($first_entry)
			{
				$curr_range_start = $entry->min_packed;
				$curr_range_end = $entry->max_packed;
				$int_size = $entry->int_size;
				$first_entry = false;
			}
			// These are consecutive, combine & keep going, not done yet...
			elseif (Packed::inc($prev_cidr->max_packed) === $entry->min_packed)
			{
				$curr_range_end = $entry->max_packed;
			}
			// These are not consecutive, spit out prev CIDR & start a new range to evaluate...
			else
			{
				foreach (CIDR::build($curr_range_start, $curr_range_end, $int_size) AS $temp)
					$cleansed_cidrs[] = new CIDR($temp);
				$curr_range_start = $entry->min_packed;
				$curr_range_end = $entry->max_packed;
			}
			$prev_cidr = $entry;
		}
		// Wrapup last range...
		if (!$first_entry)
			foreach (CIDR::build($curr_range_start, $curr_range_end, $int_size) AS $temp)
				$cleansed_cidrs[] = new CIDR($temp);

		// Update obj list of CIDRs
		return $cleansed_cidrs;
	}

	// Write the cleansed list to a text file.
	public function write($file)
	{
		$fp = fopen($file, 'a');
		foreach($this->cidrs_ipv4 AS $entry)
			fputs($fp, $this->command . $entry->to_text() . "\n");
		foreach($this->cidrs_ipv6 AS $entry)
			fputs($fp, $this->command . $entry->to_text() . "\n");
		fclose($fp);
	}
}
