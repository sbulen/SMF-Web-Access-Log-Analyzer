<?php
/**
 *	Logic for the Web Access Log Analyzer mod hooks.
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

// If we are outside SMF throw an error.
if (!defined('SMF')) {
    die('Hacking attempt...');
}

/**
 *
 * Hook function - Add admin menu functions.
 *
 * Hook: integrate_admin_areas
 *
 * @param array $menu
 *
 * @return null
 *
 */
function wala_admin_menu(&$menu)
{
	global $txt;

	loadLanguage('WALAnalyzer');

	$title = $txt['wala_title'];

	// Add to the main menu
	$menu['maintenance']['areas']['wala'] = array(
		'label' => $title,
		'file' => 'WALAnalyzer.php',
		'function' => 'wala_main',
		'icon' => 'reports',
		'permission' => 'admin_forum',
		'subsections' => array(
			'load' => array($txt['wala_load']),
		    'reports' => array($txt['wala_reports']),
		),
	);
}

/**
 *
 * Hook function - Uses an xml action so templates are not refreshed.
 *
 * Hook: integrate_simple_actions
 *
 * @return null
 */


function wala_simple_actions(&$simpleActions, &$simpleAreas, &$simpleSubActions, &$extraParams, &$xmlActions)
{
	$xmlActions[] = 'walachunk';
	$xmlActions[] = 'walaprep';
	$xmlActions[] = 'walaimport';
	$xmlActions[] = 'walamemb';
	$xmlActions[] = 'walamattr';
	$xmlActions[] = 'walalattr';
}

/**
 *
 * Hook function - Add the WALA subactions to the subaction array - xml version.
 *
 * Hook: integrate_XMLhttpMain_subActions
 *
 * @param array $subaction_array
 * @return null
 *
 */
function wala_XMLhttpMain_subActions(&$subaction_array)
{
	$subaction_array['walachunk'] = 'wala_chunk';
	$subaction_array['walaprep'] = 'wala_prep';
	$subaction_array['walaimport'] = 'wala_import';
	$subaction_array['walamemb'] = 'wala_members';
	$subaction_array['walamattr'] = 'wala_memb_attr';
	$subaction_array['walalattr'] = 'wala_log_attr';
}

/**
 *
 * Hook function - preloads WALAnalyzer.php so function calls work.
 *
 * Hook: integrate_pre_load
 *
 * @return null
 */
function wala_preload()
{
	global $sourcedir;

	require_once($sourcedir . '/WALAnalyzer.php');
}
