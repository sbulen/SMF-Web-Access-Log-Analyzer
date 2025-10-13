<?php
/**
 *	Template for admin functions for the Web Access Log Analyzer mod for SMF.
 *
 *	Copyright 2025 Shawn Bulen
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
 * A page to load the various source tables for WALA.
 */

function template_wala_load()
{
	global $context, $scripturl, $txt, $boardurl;

	echo '
	<div>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['wala_load'], '
			</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">';

	echo '
			<fieldset class="wala_file_group">
				<legend>', $txt['wala_file_asn'], '</legend>
					<label for="wala_file_asn">' . $txt['file'] . ':<div id="wala_file_asn" class="wala_file_name">' . $context['wala_status']['asn']['file_name'] . '</div></label>
					<label for="wala_update_asn">' . $txt['wala_updated'] . ':<div id="wala_update_asn" class="wala_date">' . $context['wala_status']['asn']['last_proc_time'] . '</div></label>
					<input type="file" name="file_asn_gz" id="file_asn_gz" class="wala_file_input" value="file_asn_gz" size="80">
					<div id="file_asn_status" class="wala_file_status"></div>
					<div id="file_asn_wheel" class="wala_file_wheel"><img src="' . $boardurl . '/Themes/default/images/loading_sm.gif"/></div>
					<input type="submit" id="file_asn_upload" value="' . $txt['upload'] . '" class="button wala_button"  onclick="walaUpload(\'asn\')">
			</fieldset>
			<fieldset class="wala_file_group">
				<legend>', $txt['wala_file_country'], '</legend>
					<label for="wala_file_country">' . $txt['file'] . ':<div id="wala_file_country" class="wala_file_name">' . $context['wala_status']['country']['file_name'] . '</div></label>
					<label for="wala_update_country">' . $txt['wala_updated'] . ':<div id="wala_update_country" class="wala_date">' . $context['wala_status']['country']['last_proc_time'] . '</div></label>
					<input type="file" name="file_country_gz" id="file_country_gz" class="wala_file_input" value="file_country_gz" size="80">
					<div id="file_country_status" class="wala_file_status"></div>
					<div id="file_country_wheel" class="wala_file_wheel"><img src="' . $boardurl . '/Themes/default/images/loading_sm.gif"/></div>
					<input type="submit" id="file_country_upload" value="' . $txt['upload'] . '" class="button wala_button"  onclick="walaUpload(\'country\')">
			</fieldset>
			<fieldset class="wala_file_group">
				<legend>', $txt['wala_member_file'], '</legend>
					<label for="wala_file_log" style="visibility:hidden;">' . $txt['file'] . ':<div id="wala_file_log" class="wala_file_name"></div></label>
					<label for="wala_update_member">' . $txt['wala_updated'] . ':<div id="wala_update_member" class="wala_date">' . $context['wala_status']['member']['last_proc_time'] . '</div></label>
					<div class="wala_file_input"></div>
					<div id="file_member_status" class="wala_file_status"></div>
					<div id="file_member_wheel" class="wala_file_wheel"><img src="' . $boardurl . '/Themes/default/images/loading_sm.gif"/></div>
					<input type="submit" id="file_member" value="' . $txt['wala_reload'] . '" class="button wala_button"  onclick="walaMemberSync()">
			</fieldset>
			<fieldset class="wala_file_group">
				<legend>', $txt['wala_access_log'], '</legend>
					<label for="wala_file_log">' . $txt['file'] . ':<div id="wala_file_log" class="wala_file_name">' . $context['wala_status']['log']['file_name'] . '</div></label>
					<label for="wala_update_log">' . $txt['wala_updated'] . ':<div id="wala_update_log" class="wala_date">' . $context['wala_status']['log']['last_proc_time'] . '</div></label>
					<input type="file" name="file_log_gz" id="file_log_gz" class="wala_file_input" value="file_log_gz" size="80">
					<div id="file_log_status" class="wala_file_status"></div>
					<div id="file_log_wheel" class="wala_file_wheel"><img src="' . $boardurl . '/Themes/default/images/loading_sm.gif"/></div>
					<input type="submit" id="file_log_upload" value="' . $txt['upload'] . '" class="button wala_button"  onclick="walaUpload(\'log\')">
			</fieldset>';

	echo '
		</div>
	</div>';
}

/**
 * A page to run WALA reports.
 */

function template_wala_reports()
{
	global $context, $scripturl, $txt;

	echo '
		<form action="', $scripturl, '?action=admin;area=wala;sa=reports" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['wala_reports'], '
				</h3>
			</div>
			<div class="windowbg">';

	echo '
				<fieldset>
					<legend>', $txt['wala_report_select'], '</legend>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<select name="wala_report_selection" id="wala_report_selection">';

	// Report options....
	foreach ($context['wala_reports'] AS $key => $details) {
		if (isset($context['wala_report_selected']) && ($key == $context['wala_report_selected']))
			$selected = 'selected';
		else
			$selected = '';
		echo '
						<option value="' . $key . '" ' . $selected . '>' . $txt[$key] . '</option>';
	}

	echo '
					</select>
					<input type="submit" name="wala_report_submit" value="' . $txt['wala_submit'] . '" class="button">
				</fieldset>';

	// Display report contents here....
	template_wala_report_contents();

	echo '
			</div>
		</form>';
}

/**
 * This template spits out a report via divs based on context...
 * This will spit out any 2-dimensional array as a table.
 */

function template_wala_report_contents()
{
	global $context, $txt;

	if (empty($context['wala_report_detail']))
		return;
	if (empty($context['wala_report_hdr']))
		return;

	// Table level...
	echo '
	<div class="wala_table">';

	// Table header...
	echo '
		<div class="wala_table_hdr">';
		// Cell level...
	foreach ($context['wala_report_hdr'] AS $cell) {
		echo '
			<div class="wala_cell">'. $cell . '</div>';
	}
	echo '
		</div>';

	// Row level...
	foreach ($context['wala_report_detail'] AS $row) {
		echo '
		<div class="wala_row">';
		// Cell level...
		foreach ($row AS $cell) {
			echo '
			<div class="wala_cell">'. $cell . '</div>';
		}
		echo '
		</div>';
	}
	echo '
	</div>';
}
