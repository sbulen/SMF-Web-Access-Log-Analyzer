<?php
/**
 *	Main logic for the Web Access Log Analyzer mod for SMF..
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
 * browse_feeds - action.
 *
 * Primary action called from the admin menu for managing the RSS feeds.
 * Sets subactions & list columns & figures out if which subaction to call.
 *
 * Action: admin
 * Area: wala
 *
 * @return null
 *
 */
function wala_main() {
	global $txt, $context, $sourcedir;

	// You have to be able to moderate the forum to do this.
	isAllowedTo('admin_forum');

	// Stuff we'll need around...
	loadLanguage('WALAnalyzer');
	loadCSSFile('walanalyzer.css');

	// Setup the template stuff we'll need.
	loadTemplate('WALAnalyzerMaint');

	// Everyone needs this...
	require_once($sourcedir . '/WALAnalyzerModel.php');

	// Sub actions...
	$subActions = array(
		'load' => 'wala_load',
		'reports' => 'wala_reports',
	);

	// Pick the correct sub-action.
	if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
		$context['sub_action'] = $_REQUEST['sa'];
	else
		$context['sub_action'] = 'load';

	$_REQUEST['sa'] = $context['sub_action'];

	// This uses admin tabs
	$context[$context['admin_menu_name']]['tab_data']['title'] = $txt['wala_title'];

	// Use the short description when viewing reports...
	if ($context['sub_action'] == 'load')
		$context[$context['admin_menu_name']]['tab_data']['description'] = $txt['wala_desc'];
	else
		$context[$context['admin_menu_name']]['tab_data']['description'] = $txt['wala_desc_short'];

	// Set the page title
	$context['page_title'] = $txt['wala_title'];

	// Finally fall through to what we are doing.
	call_helper($subActions[$context['sub_action']]);
}

/**
 * show_feed_status.
 *
 * Action: admin
 * Area: wala
 * Subaction: load
 *
 * @return null
 *
 */
function wala_load() {
	global $txt, $context, $sourcedir, $scripturl, $modSettings;

	// You have to be able to admin the forum to do this.
	isAllowedTo('admin_forum');

	// Make sure the right person is putzing...
	checkSession('get');

	// Base max chunk size on max post size & upload_max_filesize, whichever is lower...
	// Default to 512K if not otherwise found.
	$post_max_size = trim(ini_get('post_max_size'));
	if (empty($post_max_size))
		$post_max_size = 1024*512;
	else {
		$unit = strtoupper(substr($post_max_size, -1));
		$value = (int) substr($post_max_size, 0, -1);
		if ($unit === 'G')
			$post_max_size = $value * 1024**3;
		elseif ($unit === 'M')
			$post_max_size = $value * 1024**2;
		elseif ($unit === 'K')
			$post_max_size = $value * 1024;
		else
			$post_max_size = (int) $post_max_size;
	}

	$upload_max_filesize = trim(ini_get('upload_max_filesize'));
	if (empty($upload_max_filesize))
		$upload_max_filesize = 1024*512;
	else {
		$unit = strtoupper(substr($upload_max_filesize, -1));
		$value = (int) substr($upload_max_filesize, 0, -1);
		if ($unit === 'G')
			$upload_max_filesize = $value * 1024**3;
		elseif ($unit === 'M')
			$upload_max_filesize = $value * 1024**2;
		elseif ($unit === 'K')
			$upload_max_filesize = $value * 1024;
		else
			$upload_max_filesize = (int) $upload_max_filesize;
	}

	// Need elbow room, lotsa other gunk in there...
	$wala_chunk_size = (int) (min($upload_max_filesize, $post_max_size) * 0.9);

	// JS vars for user info display
	addJavaScriptVar('wala_chunk_size', $wala_chunk_size, false);
	addJavaScriptVar('wala_str_loader', $txt['wala_loader'], true);
	addJavaScriptVar('wala_str_uploaded', $txt['wala_uploaded'], true);
	addJavaScriptVar('wala_str_prep', $txt['wala_prep'], true);
	addJavaScriptVar('wala_str_imported', $txt['wala_imported'], true);
	addJavaScriptVar('wala_str_attribution', $txt['wala_attribution'], true);
	addJavaScriptVar('wala_str_done', $txt['wala_done'], true);
	addJavaScriptVar('wala_str_success', $txt['wala_success'], true);
	addJavaScriptVar('wala_str_failed', $txt['wala_failed'], true);
	addJavaScriptVar('wala_str_error_chunk', $txt['wala_error_chunk'], true);

	// For file xfers
	loadJavaScriptFile('wala_file_xfers.js');

	// Load up context with the file status data
	$status_info = get_status();
	foreach ($status_info AS $table) {
		$context['wala_status'][$table['file_type']]['file_name'] = $table['file_name'];
		$context['wala_status'][$table['file_type']]['last_proc_time'] = !empty($table['last_proc_time']) ? timeformat($table['last_proc_time']) : '';
	}

	// Set up some basics....
	$context['url_start'] = '?action=admin;area=wala;sa=load';
	$context['page_title'] = $txt['wala_load'];
	$context['sub_template'] = 'wala_load';
}

/**
 * wala_reports
 *
 * Action: admin
 * Area: wala
 * Subaction: reports
 *
 * @return null
 *
 */
function wala_reports() {
	global $context, $smcFunc, $txt;

	// You have to be able to moderate the forum to do this.
	isAllowedTo('admin_forum');

	// Array with available reports
	// Note some specific mysql syntax is xlated to pg later (e.g., from_unixtime())
	$context['wala_reports'] = array(
		'wala_rpt_ureqsxcountryui' => array(
			'hdr' => array('requests', 'country', 'user count', 'last login'),
			'sql' =>'WITH waltots AS (SELECT COUNT(*) AS requests, country FROM {db_prefix}wala_web_access_log WHERE status <> 403 AND status <> 429 GROUP BY country), memtots AS (SELECT COUNT(*) AS user_count, MAX(last_login) AS last_user_login, country FROM {db_prefix}wala_members GROUP BY country)  SELECT waltots.requests, waltots.country, memtots.user_count, FROM_UNIXTIME(memtots.last_user_login) AS last_user_login FROM waltots LEFT JOIN memtots ON (waltots.country = memtots.country) ORDER BY waltots.requests DESC LIMIT 500',
		),
		'wala_rpt_areqsxcountryui' => array(
			'hdr' => array('requests', 'country', 'user count', 'last login'),
			'sql' =>'WITH waltots AS (SELECT COUNT(*) AS requests, country FROM {db_prefix}wala_web_access_log GROUP BY country), memtots AS (SELECT COUNT(*) AS user_count, MAX(last_login) AS last_user_login, country FROM {db_prefix}wala_members GROUP BY country) SELECT waltots.requests, waltots.country, memtots.user_count, FROM_UNIXTIME(memtots.last_user_login) AS last_user_login FROM waltots LEFT JOIN memtots ON (waltots.country = memtots.country) ORDER BY waltots.requests DESC LIMIT 500',
		),
		'wala_rpt_ureqsxasnui' => array(
			'hdr' => array('requests', 'asn', 'asn name', 'user count', 'last login'),
			'sql' =>'WITH waltots AS (SELECT COUNT(*) AS requests, asn FROM {db_prefix}wala_web_access_log WHERE status <> 403 AND status <> 429 GROUP BY asn), memtots AS (SELECT COUNT(*) AS user_count, MAX(last_login) AS last_user_login, asn FROM {db_prefix}wala_members GROUP BY asn) SELECT waltots.requests, waltots.asn, a.asn_name, memtots.user_count, FROM_UNIXTIME(memtots.last_user_login) AS last_user_login FROM waltots INNER JOIN {db_prefix}wala_asns a ON (waltots.asn = a.asn) LEFT JOIN memtots ON (waltots.asn = memtots.asn) ORDER BY waltots.requests DESC LIMIT 500',
		),
		'wala_rpt_areqsxasnui' => array(
			'hdr' => array('requests', 'asn', 'asn name', 'user count', 'last login'),
			'sql' =>'WITH waltots AS (SELECT COUNT(*) AS requests, asn FROM {db_prefix}wala_web_access_log GROUP BY asn), memtots AS (SELECT COUNT(*) AS user_count, MAX(last_login) AS last_user_login, asn FROM {db_prefix}wala_members GROUP BY asn) SELECT waltots.requests, waltots.asn, a.asn_name, memtots.user_count, FROM_UNIXTIME(memtots.last_user_login) AS last_user_login FROM waltots INNER JOIN {db_prefix}wala_asns a ON (waltots.asn = a.asn) LEFT JOIN memtots ON (waltots.asn = memtots.asn) ORDER BY waltots.requests DESC LIMIT 500',
		),
		'wala_rpt_ureqsxagent' => array(
			'hdr' => array('agent', 'requests'),
			'sql' =>'SELECT agent, COUNT(*) as requests FROM {db_prefix}wala_web_access_log WHERE status <> 403 AND status <> 429 GROUP BY agent ORDER BY requests DESC LIMIT 500',
		),
		'wala_rpt_areqsxagent' => array(
			'hdr' => array('agent', 'requests'),
			'sql' =>'SELECT agent, COUNT(*) as requests FROM {db_prefix}wala_web_access_log GROUP BY agent ORDER BY requests DESC LIMIT 500',
		),
		'wala_rpt_ureqsxuser' => array(
			'hdr' => array('username', 'requests'),
			'sql' =>'SELECT username, COUNT(*) as requests FROM {db_prefix}wala_web_access_log WHERE status <> 403 AND status <> 429 GROUP BY username ORDER BY requests DESC LIMIT 500',
		),
		'wala_rpt_areqsxuser' => array(
			'hdr' => array('username', 'requests'),
			'sql' =>'SELECT username, COUNT(*) as requests FROM {db_prefix}wala_web_access_log GROUP BY username ORDER BY requests DESC LIMIT 500',
		),
		'wala_rpt_ureqsxbrowser' => array(
			'hdr' => array('browser', 'requests'),
			'sql' =>'SELECT browser_ver, COUNT(*) as requests FROM {db_prefix}wala_web_access_log WHERE status <> 403 AND status <> 429 GROUP BY browser_ver ORDER BY requests DESC LIMIT 500',
		),
		'wala_rpt_areqsxbrowser' => array(
			'hdr' => array('browser', 'requests'),
			'sql' =>'SELECT browser_ver, COUNT(*) as requests FROM {db_prefix}wala_web_access_log GROUP BY browser_ver ORDER BY requests DESC LIMIT 500',
		),
		'wala_rpt_uipsxcountry' => array(
			'hdr' => array('country', 'ips'),
			'sql' =>'SELECT country, COUNT(DISTINCT ip_packed) AS ips FROM {db_prefix}wala_web_access_log WHERE status <> 403 AND status <> 429 GROUP BY country ORDER BY ips DESC LIMIT 500',
		),
		'wala_rpt_aipsxcountry' => array(
			'hdr' => array('country', 'ips'),
			'sql' =>'SELECT country, COUNT(DISTINCT ip_packed) AS ips FROM {db_prefix}wala_web_access_log GROUP BY country ORDER BY ips DESC LIMIT 500',
		),
		'wala_rpt_uipsxasn' => array(
			'hdr' => array('asn', 'asn name', 'ips'),
			'sql' =>'SELECT a.asn, a.asn_name, COUNT(DISTINCT ip_packed) AS ips FROM {db_prefix}wala_web_access_log wal INNER JOIN {db_prefix}wala_asns a ON (wal.asn = a.asn) WHERE status <> 403 AND status <> 429 GROUP BY a.asn ORDER BY ips DESC LIMIT 500',
		),
		'wala_rpt_aipsxasn' => array(
			'hdr' => array('asn', 'asn name', 'ips'),
			'sql' =>'SELECT a.asn, a.asn_name, COUNT(DISTINCT ip_packed) AS ips FROM {db_prefix}wala_web_access_log wal INNER JOIN {db_prefix}wala_asns a ON (wal.asn = a.asn) GROUP BY a.asn ORDER BY ips DESC LIMIT 500',
		),
		'wala_rpt_ulikesxcountry' => array(
			'hdr' => array('country', 'view likes'),
			'sql' =>'SELECT country, COUNT(*) AS requests FROM {db_prefix}wala_web_access_log WHERE status <> 403 AND status <> 429 AND request LIKE \'action=likes%\' AND request LIKE \'%sa=view%\' GROUP BY country ORDER BY requests DESC LIMIT 500',
		),
		'wala_rpt_alikesxcountry' => array(
			'hdr' => array('country', 'view likes'),
			'sql' =>'SELECT country, COUNT(*) AS requests FROM {db_prefix}wala_web_access_log WHERE request LIKE \'%action=likes%\' AND request LIKE \'%sa=view%\' GROUP BY country ORDER BY requests DESC LIMIT 500',
		),
		'wala_rpt_ulikesxasn' => array(
			'hdr' => array('asn', 'asn name', 'view likes'),
			'sql' =>'SELECT a.asn, a.asn_name, COUNT(*) AS requests FROM {db_prefix}wala_web_access_log wal INNER JOIN {db_prefix}wala_asns a ON (wal.asn = a.asn) WHERE status <> 403 AND status <> 429 AND request LIKE \'%action=likes%\' AND request LIKE \'%sa=view%\' GROUP BY a.asn ORDER BY requests DESC LIMIT 500',
		),
		'wala_rpt_alikesxasn' => array(
			'hdr' => array('asn', 'asn name', 'view likes'),
			'sql' =>'SELECT a.asn, a.asn_name, COUNT(*) AS requests FROM {db_prefix}wala_web_access_log wal INNER JOIN {db_prefix}wala_asns a ON (wal.asn = a.asn) WHERE request LIKE \'%action=likes%\' AND request LIKE \'%sa=view%\' GROUP BY a.asn ORDER BY requests DESC LIMIT 500',
		),
		'wala_rpt_userxasn' => array(
			'hdr' => array('asn', 'asn name', 'users'),
			'sql' =>'SELECT a.asn, a.asn_name, COUNT(*) as users FROM {db_prefix}wala_members m INNER JOIN {db_prefix}wala_asns a ON (m.asn = a.asn) GROUP BY a.asn, a.asn_name ORDER BY users DESC LIMIT 500',
		),
		'wala_rpt_userxcountry' => array(
			'hdr' => array('country', 'users'),
			'sql' => 'SELECT country, COUNT(*) as users FROM {db_prefix}wala_members GROUP BY country ORDER BY users DESC LIMIT 500',
		),
	);

	// Confirm they're OK being here...
	if (!empty($_POST))
		checkSession('post');

	// Report request?
	$context['wala_report_detail'] = array();
	if (!empty($_POST)) {
		// Make sure it's a valid request...
		if (!empty($_POST['wala_report_selection']) && array_key_exists($_POST['wala_report_selection'], $context['wala_reports'])) {
			$context['wala_report_detail'] = wala_report_request($context['wala_reports'][$_POST['wala_report_selection']]['sql']);
			$context['wala_report_hdr'] = $context['wala_reports'][$_POST['wala_report_selection']]['hdr'];
			$context['wala_report_selected'] = $_POST['wala_report_selection'];
		}
	}

	// Set up some basics....
	$context['url_start'] = '?action=admin;area=wala;sa=reports';
	$context['page_title'] = $txt['wala_reports'];
	$context['sub_template'] = 'wala_reports';
}

/**
 * WALA chunk respose - subaction for uploaded file chunk.
 * Used when loading dbip_asn, dbip_country & the access log.
 * Load the file chunk sent by the fetch api.
 *
 * Action: xmlhttp
 * Subaction: walachunk
 *
 * @return null
 *
 */
function wala_chunk() {
	global $context, $cachedir;

	// Make sure it's OK they're here...
	checkSession();

	// if file system or post issues encountered, return a 500
	$issues = false;

	// Let's use our own subdir...
	$temp_dir = $cachedir . '/wala';
	if (!is_dir($temp_dir)) {
		if (@mkdir($temp_dir, 0755) === false)
			$issues = true;
	}

	// If POST fails due to network settings issues, these aren't set...
	$file_name = '';
	if (isset($_POST['name']) && is_string($_POST['name']))
		$file_name = $_POST['name'];
	else
		$issues = true;

	$file_index = 0;
	if (isset($_POST['index']) && is_numeric($_POST['index']))
		$file_index = $_POST['index'];
	else
		$issues = true;

	$file_type = '';
	if (isset($_POST['file_type']) && is_string($_POST['file_type']))
		$file_type = $_POST['file_type'];
	else
		$issues = true;

	// Since this is the start of the whole process, clear out all similar filenames
	// in case anything left over from previous failed attempts - .csvs and .gzs, all parts#s.
	if ($file_index == 1) {
		if (substr($file_name, -3) === '.gz')
			$del_pattern = substr($file_name, 0, -3);
		else
			$del_pattern = $file_name;
		
		$files = glob($temp_dir . '/' . $del_pattern . '*');
		foreach($files as $file){
			if(is_file($file)) {
				@unlink($file);
			}
		}
	}

	// Move the current chunk to tmp
	if (@move_uploaded_file($_FILES['chunk']['tmp_name'], $temp_dir . '/' . $file_name . '.chunk.' . $file_index) === false)
		$issues = true;

	// For a simple generic yes/no response
	$context['sub_template'] = 'generic_xml';

	if ($issues) {
		$context['xml_data'][] = array('value' => 'FAILURE');
		send_http_status(500);
	}
	else
		$context['xml_data'][] = array('value' => 'OK');
}

/**
 * WALA_prep - subaction to combine the gz chunks, decompress & prep new csv chunks for import.
 * Used when loading dbip_asn, dbip_country & the access log.
 *
 * Action: xmlhttp
 * Subaction: walaprep
 *
 * @return null
 *
 */
function wala_prep() {
    global $context, $txt, $sourcedir, $cachedir;

	// Make sure it's OK they're here...
	checkSession();

	// If file system or post issues encountered, return a 500
	$issues = false;

	// Make sure you got all the pieces...
	$temp_dir = $cachedir . '/wala';
	if (!is_dir($temp_dir))
		$issues = true;

	$file_name = '';
	if (isset($_POST['name']) && is_string($_POST['name']))
		$file_name = $_POST['name'];
	else
		$issues = true;

	$total_chunks = 0;
	if (isset($_POST['total_chunks']) && is_numeric($_POST['total_chunks']))
		$total_chunks = $_POST['total_chunks'];
	else
		$issues = true;

	$file_type = '';
	if (isset($_POST['file_type']) && is_string($_POST['file_type']))
		$file_type = $_POST['file_type'];
	else
		$issues = true;

	// Build the gz file from the chunks...
	$file_path = $temp_dir . '/' . $file_name . '.chunk.*';
	$file_parts = glob($file_path);
	sort($file_parts, SORT_NATURAL);

	$final_file_name = $temp_dir . '/' . $file_name;
	$final_file = @fopen($final_file_name, 'w');
	if ($final_file === false)
		$issues = true;

	foreach ($file_parts as $file_part) {
		$chunk = file_get_contents($file_part);
		if ($chunk === false)
			$issues = true;
		else
			if (@fwrite($final_file, $chunk) === false)
				$issues = true;
		// Clean up after ourselves either way
		@unlink($file_part);
	}

	@fclose($final_file);

	if ($total_chunks != count($file_parts)) {
		// It's not usable...
		@unlink($final_file_name);
		$issues = true;
	}

	// Now that we have a readable .gz, break it up into .csvs
	static $commit_rec_count = 25000;
	$reccount = 0;
	$index = 1;

	// If gz filename ended in .gz, strip it for csv name...
	if (substr($file_name, -3) === '.gz')
		$filename_csv = substr($file_name, 0, -3);
	else
		$filename_csv = $file_name;

	if (!$issues) {
		$fpgz = @gzopen($temp_dir . '/' . $file_name, 'r');
		$fpcsv = @fopen($temp_dir . '/' . $filename_csv . '.chunk.' . $index, 'w');

		$buffer = @fgets($fpgz);
		while ($buffer !== false) {
			$reccount++;
			if ($reccount >= $commit_rec_count) {
				fclose($fpcsv);
				$reccount = 0;
				$index++;
				$fpcsv = @fopen($temp_dir . '/' . $filename_csv . '.chunk.' . $index, 'w');
			}
			@fwrite($fpcsv, $buffer);
			$buffer = @fgets($fpgz);
		}
		@fclose($fpcsv);
		@gzclose($fpgz);
		// Don't need this anymore...
		@unlink($final_file_name);
	}

	// Truncate target table...
	require_once($sourcedir . '/WALAnalyzerModel.php');
	if (!$issues) {
		if ($file_type === 'asn')
			truncate_dbip_asn();
		elseif ($file_type === 'country')
			truncate_dbip_country();
		elseif ($file_type === 'log')
			truncate_web_access_log();
	}

	// For a simple generic yes/no response
	$context['sub_template'] = 'generic_xml';

	if ($issues) {
		$context['xml_data'][] = array('value' => 'FAILURE');
		send_http_status(500);
	}
	else
		$context['xml_data'][] = array('value' => 'OK ' . $index . ' chunks');
}

/**
 * WALA_import - subaction to combine the chunks & prep csv chunks for import.
 * Used when loading dbip_asn, dbip_country & the access log.
 *
 * Action: xmlhttp
 * Subaction: walaimport
 *
 * @return null
 *
 */
function wala_import() {
	global $context, $txt, $sourcedir, $cachedir;

	// Make sure it's OK they're here...
	checkSession();

	// If file system or post issues encountered, return a 500
	$issues = false;

	// Gonna need this...
	require_once($sourcedir . '/WALAnalyzerModel.php');

	// Make sure you got all the pieces...
	$temp_dir = $cachedir . '/wala';
	if (!is_dir($temp_dir))
		$issues = true;

	$file_name = '';
	if (isset($_POST['name']) && is_string($_POST['name']))
		$file_name = $_POST['name'];
	else
		$issues = true;

	$total_chunks = 0;
	if (isset($_POST['total_chunks']) && is_numeric($_POST['total_chunks']))
		$total_chunks = $_POST['total_chunks'];
	else
		$issues = true;

	$index = 0;
	if (isset($_POST['index']) && is_numeric($_POST['index']))
		$index = $_POST['index'];
	else
		$issues = true;

	$file_type = '';
	if (isset($_POST['file_type']) && is_string($_POST['file_type']))
		$file_type = $_POST['file_type'];
	else
		$issues = true;

	// If gz filename ended in .gz, strip it...
	if (substr($file_name, -3) === '.gz')
		$filename_csv = substr($file_name, 0, -3);
	else
		$filename_csv = $file_name;

	// Build the file from the info passed
	$filename_csv .= '.chunk.' . $index;

	// Disable autocommits for mass inserts (can hide errors, though...)
	start_transaction();

	// Now choose what to load based on file_type
	if (!$issues) {
		if ($file_type === 'asn')
			$issues = wala_load_asn($temp_dir . '/' . $filename_csv);
		elseif ($file_type === 'country')
			$issues = wala_load_country($temp_dir . '/' . $filename_csv);
		elseif ($file_type === 'log')
			$issues = wala_load_log($temp_dir . '/' . $filename_csv);

		// If issues found here, it's an invalid file format...
		// Logging error because we're not in a normal theme context...
		if ($issues) {
			loadLanguage('WALAnalyzer');
			log_error($txt['wala_file_error'], 'general', __FILE__, __LINE__);
		}
		commit();
	}

	// Don't need this one anymore either...
	@unlink($temp_dir . '/' . $filename_csv);

	// If we're done, update the file status info...
	if (!$issues && ($index === $total_chunks)) {
		if ($file_type === 'asn') {
			// Also load wala_asns from wala_dbip_asn...
			load_asn_names();
			update_status('asn', $file_name, time());
		}
		elseif ($file_type === 'country')
			update_status('country', $file_name, time());
		elseif ($file_type === 'log') {
			update_status('log', $file_name, time());
		}
	}

	// For a simple generic yes/no response
	$context['sub_template'] = 'generic_xml';

	if ($issues) {
		$context['xml_data'][] = array('value' => 'FAILURE');
		send_http_status(500);
	}
	else
		$context['xml_data'][] = array('value' => 'OK');
}

/**
 * WALA_members - subaction to load member reporting table from smf member table in chunks.
 * Used to load smf_members table to the wala_members table, to assign asn & country & also cache a few other attributes.
 *
 * Action: xmlhttp
 * Subaction: walamemb
 *
 * @return null
 *
 */
function wala_members() {
	global $context, $txt, $sourcedir, $cachedir;

	// Make sure it's OK they're here...
	checkSession();

	// If file system or post issues encountered, return a 500
	$issues = false;

	$index = 0;
	if (isset($_POST['index']) && is_numeric($_POST['index']))
		$index = (int) $_POST['index'];
	else
		$issues = true;

	// Gonna need this...
	require_once($sourcedir . '/WALAnalyzerModel.php');
	
	// How many members total?
	$reccount = count_smf_members();

	// How many chunks total?  Not too big...
	$commit_rec_count = ceil($reccount/20);
	if ($commit_rec_count > 20000)
		$commit_rec_count = 20000;
	$chunkct = ceil($reccount/$commit_rec_count);

	// Disable autocommits for mass inserts (can hide errors, though...)
	start_transaction();

	// Truncate target table...
	if (!$issues && ($index ==	1)) {
		truncate_members();
		commit();
	}

	// Copy over a set of members...
	$start = ($index - 1) * $commit_rec_count;
	$inserts = array();
	if (!$issues) {
		$inserts = get_smf_members($start, $commit_rec_count);
		insert_members($inserts);
		commit();
	}

	// If we're done, update the file status info...
	if (!$issues && ($index ==	$chunkct)) {
		update_status('member', '---', time());
		commit();
	}

	// For a simple generic yes/no response
	$context['sub_template'] = 'generic_xml';

	if ($issues) {
		$context['xml_data'][] = array('value' => 'FAILURE');
		send_http_status(500);
	}
	else
		$context['xml_data'][] = array('value' => 'OK ' . $chunkct . ' chunks');
}

/**
 * WALA_member_attr - load attributes to the newly loaded member file.
 * Looking up by IP, load ASN & Country.
 *
 * Action: xmlhttp
 * Subaction: walamattr
 *
 * @return null
 *
 */
function wala_memb_attr() {

    global $context, $sourcedir;

	// Make sure it's OK they're here...
	checkSession();

	// If file system or post issues encountered, return a 500
	$issues = false;

	$index = 0;
	if (isset($_POST['index']) && is_numeric($_POST['index']))
		$index = (int) $_POST['index'];
	else
		$issues = true;

	// Gonna need this...
	require_once($sourcedir . '/WALAnalyzerModel.php');

	if (!$issues) {
		// How many chunks total?  Not too big...
		// Even a small chunk of users, sorted by IP, can retrieve a large # of asn/country rows
		$reccount = count_smf_members();
		$commit_rec_count = ceil($reccount/20);
		if ($commit_rec_count > 20000)
			$commit_rec_count = 20000;
		$chunkct = ceil($reccount/$commit_rec_count);

		$offset = $index * $commit_rec_count;
		$limit = $commit_rec_count;
		$members = get_wala_members($offset, $limit);
		$ips = array_column($members, 'ip_packed');
		$min_ip_packed = min($ips);
		$max_ip_packed = max($ips);
		load_asn_cache($min_ip_packed, $max_ip_packed);
		load_country_cache($min_ip_packed, $max_ip_packed);
		start_transaction();
		foreach ($members AS $member_info) {
			$member_info['asn'] = get_asn($member_info['ip_packed']);
			$member_info['country'] = get_country($member_info['ip_packed']);
			update_wala_members($member_info);
		}
		commit();
	}

	// For a simple generic yes/no response
	$context['sub_template'] = 'generic_xml';

	if ($issues) {
		$context['xml_data'][] = array('value' => 'FAILURE');
		send_http_status(500);
	}
	else
		$context['xml_data'][] = array('value' => 'OK ' . $chunkct . ' chunks');
}

/**
 * WALA_log_attr - load attributes to the newly loaded log file
 * Looking up by IP, load ASN, Country & member.
 *
 * Action: xmlhttp
 * Subaction: walalattr
 *
 * @return null
 *
 */
function wala_log_attr() {

    global $context, $sourcedir;

	// Make sure it's OK they're here...
	checkSession();

	// If file system or post issues encountered, return a 500
	$issues = false;

	$index = 0;
	if (isset($_POST['index']) && is_numeric($_POST['index']))
		$index = (int) $_POST['index'];
	else
		$issues = true;

	// Gonna need this...
	require_once($sourcedir . '/WALAnalyzerModel.php');

	if (!$issues) {
		// How many chunks total?  Not too big...
		// Even a small chunk of users, sorted by IP, can retrieve a large # of asn/country rows
		$reccount = count_web_access_log();
		$commit_rec_count = ceil($reccount/20);
		if ($commit_rec_count > 20000)
			$commit_rec_count = 20000;
		$chunkct = ceil($reccount/$commit_rec_count);

		$offset = $index * $commit_rec_count;
		$limit = $commit_rec_count;
		$log = get_web_access_log($offset, $limit);
		$ips = array_column($log, 'ip_packed');
		$min_ip_packed = min($ips);
		$max_ip_packed = max($ips);
		load_asn_cache($min_ip_packed, $max_ip_packed);
		load_country_cache($min_ip_packed, $max_ip_packed);
		load_member_cache($min_ip_packed, $max_ip_packed);
		start_transaction();
		foreach ($log AS $entry_info) {
			$entry_info['asn'] = get_asn($entry_info['ip_packed']);
			$entry_info['country'] = get_country($entry_info['ip_packed']);
			$entry_info['username'] = get_username($entry_info['ip_packed']);
			update_web_access_log($entry_info);
		}
		commit();
	}

	// For a simple generic yes/no response
	$context['sub_template'] = 'generic_xml';

	if ($issues) {
		$context['xml_data'][] = array('value' => 'FAILURE');
		send_http_status(500);
	}
	else
		$context['xml_data'][] = array('value' => 'OK ' . $chunkct . ' chunks');


}

/**
 * WALA_load_asn - load newly added asn file to db.
 *
 * Action: na - helper function
 *
 * @return bool issues found
 *
 */
function wala_load_asn($filename = '') {
	$fp = @fopen($filename, 'r');
	$buffer = @fgetcsv($fp, null, ",", "\"", "\\");
	$inserts = array();

	// $buffer[0] = ip from, display format
	// $buffer[1] = ip to, display format
	// $buffer[2] = asn
	// $buffer[3] = asn desc
	while ($buffer !== false) {
		// Uploaded from random sources????  Let's make sure we're good...
		if (!filter_var($buffer[0], FILTER_VALIDATE_IP) || !filter_var($buffer[1], FILTER_VALIDATE_IP) || !is_numeric($buffer[2]) || !is_string($buffer[3]))
			return true;

		// Note SMF deals with the inet_pton() for type inet, so just pass ip display format here...
		$inserts[] = array(
			$buffer[0],
			$buffer[1],
			$buffer[0],
			$buffer[1],
			$buffer[2],
			$buffer[3],
		);
		$buffer = @fgetcsv($fp, null, ",", "\"", "\\");
	}
	insert_dbip_asn($inserts);
	@fclose($fp);
	return false;
}

/**
 * WALA_load_country - load newly added country file to db.
 *
 * Action: na - helper function
 *
 * @return bool $issues_found
 *
 */
function wala_load_country($filename = '') {
	$fp = @fopen($filename, 'r');
	$buffer = @fgetcsv($fp, null, ",", "\"", "\\");
	$inserts = array();

	// $buffer[0] = ip from, display format
	// $buffer[1] = ip to, display format
	// $buffer[2] = two char country code
	while ($buffer !== false) {
		// Uploaded from random sources????  Let's make sure we're good...
		if (!filter_var($buffer[0], FILTER_VALIDATE_IP) || !filter_var($buffer[1], FILTER_VALIDATE_IP) || !is_string($buffer[2]))
			return true;

		// Note SMF deals with the inet_pton() for type inet, so just pass ip display format here...
		$inserts[] = array(
			$buffer[0],
			$buffer[1],
			$buffer[0],
			$buffer[1],
			$buffer[2],
		);
		$buffer = @fgetcsv($fp, null, ",", "\"", "\\");
	}
	insert_dbip_country($inserts);
	@fclose($fp);
	return false;
}

/**
 * WALA_load_log - load newly added web access log file to db.
 *
 * Action: na - helper function
 *
 * @return null
 *
 */
function wala_load_log($filename = '') {
	global $smcFunc, $cache_enable;

	$fp = @fopen($filename, 'r');
	$buffer = @fgetcsv($fp, null, " ", "\"", "\\");
	$inserts = array();

	while ($buffer !== false) {
		// datetime, in apache common log format
		$dt_string = substr($buffer[3] . $buffer[4], 1, -1);
		$dti = DateTimeImmutable::createFromFormat('d/M/Y:H:i:s P', $dt_string);
		$inserts[] = array(
			// The first fields are common when the apache standard logfile is used; ignore the others in the csv, as they vary a lot
			$buffer[0],								// ip packed
			$buffer[1],								// client (usually unused)
			$buffer[2],								// requestor (usually unused)
			substr($buffer[3], 1),					// date timestamp, strip the [
			substr($buffer[4], 0, -1),				// tz, strip the ]
			$buffer[5],								// request
			(int) $buffer[6],						// status
			(int) $buffer[7],						// size
			$buffer[8],								// referrer
			$buffer[9],								// useragent
			// These fields are calc'd here...
			$buffer[0],								// ip display
			get_request_type($buffer[5]),			// request_type
			get_agent($buffer[9]),					// agent
			get_browser_ver($buffer[9]),			// browser version
			$dti->getTimestamp(),					// dt in unix epoch format
		);
		$buffer = fgetcsv($fp, null, " ", "\"", "\\");
	}
	insert_log($inserts);
	fclose($fp);
	return false;
}

/**
 * load_asn_cache - load up the asn b-tree style cache.
 *
 * Action: na - helper function
 *
 * @params inet $min_ip_packed
 * @params inet $max_ip_packed
 *
 * @return void
 *
 */
function load_asn_cache($min_ip_packed, $max_ip_packed) {
	global $asn_cache;

	$asn_cache = array();
	$asns = get_asns($min_ip_packed, $max_ip_packed);

	$counter = 0;
	$limit = 150;
	$temp = array();
	foreach ($asns AS $asn) {
		$temp[bin2hex($asn['ip_to_packed'])] = array('ip_from_packed' => bin2hex($asn['ip_from_packed']), 'asn' => $asn['asn']);
		$counter++;;
		if ($counter >= $limit) {
			$asn_cache[bin2hex($asn['ip_to_packed'])] = $temp;
			$counter = 0;
			$temp = array();
		}
	}
	// Any stragglers?
	if (!empty($temp))
		$asn_cache[bin2hex($asn['ip_to_packed'])] = $temp;
}

/**
 * get_asn - look up the ASN from the cache
 *
 * Action: na - helper function
 *
 * @params inet $ip_packed
 *
 * @return string $asn
 *
 */
function get_asn($ip_packed) {
	global $asn_cache;

	$ip_hex = bin2hex($ip_packed);
	$asn =  '';
	foreach($asn_cache AS $ip_to => $layer2) {
		if (($ip_hex <= $ip_to) && (strlen($ip_hex) == strlen($ip_to))) {
			foreach($layer2 AS $ip_to2 => $data) {
				if (($ip_hex <= $ip_to2) && (strlen($ip_hex) == strlen($ip_to2))) {
					if ($ip_hex >= $data['ip_from_packed'])
						$asn = $data['asn'];
					break 2;
				}
			}
		}
	}
	return $asn;
}

/**
 * load_country_cache - load up the asn b-tree style cache.
 * Passed by reference to avoid pushing on/off the stack.
 *
 * Action: na - helper function
 *
 * @params inet $min_ip_packed
 * @params inet $max_ip_packed
 *
 * @return void
 *
 */
function load_country_cache($min_ip_packed, $max_ip_packed) {
	global $country_cache;

	$country_cache = array();
	$countries = get_countries($min_ip_packed, $max_ip_packed);

	$counter = 0;
	$limit = 200;
	$temp = array();
	foreach ($countries AS $country) {
		$temp[bin2hex($country['ip_to_packed'])] = array('ip_from_packed' => bin2hex($country['ip_from_packed']), 'country' => $country['country']);
		$counter++;;
		if ($counter >= $limit) {
			$country_cache[bin2hex($country['ip_to_packed'])] = $temp;
			$counter = 0;
			$temp = array();
		}
	}
	// Any stragglers?
	if (!empty($temp))
		$country_cache[bin2hex($country['ip_to_packed'])] = $temp;
}

/**
 * get_country - look up the country from the cache
 *
 * Action: na - helper function
 *
 * @params inet $ip_packed
 *
 * @return string $country
 *
 */
function get_country($ip_packed) {
	global $country_cache;

	$ip_hex = bin2hex($ip_packed);
	$country =  '';
	foreach($country_cache AS $ip_to => $layer2) {
		if (($ip_hex <= $ip_to) && (strlen($ip_hex) == strlen($ip_to))) {
			foreach($layer2 AS $ip_to2 => $data) {
				if (($ip_hex <= $ip_to2) && (strlen($ip_hex) == strlen($ip_to2))) {
					if ($ip_hex >= $data['ip_from_packed'])
						$country = $data['country'];
					break 2;
				}
			}
		}
	}
	return $country;
}

/**
 * load_member_cache - load up the member cache.
 * Passed by reference to avoid pushing on/off the stack.
 *
 * Action: na - helper function
 *
 * @params inet $min_ip_packed
 * @params inet $max_ip_packed
 *
 * @return void
 *
 */
function load_member_cache($min_ip_packed, $max_ip_packed) {
	global $member_cache;

	$member_cache = array();
	$members = get_member_ips($min_ip_packed, $max_ip_packed);
	foreach ($members AS $member) {
		$member_cache[bin2hex($member['ip_packed'])] = $member['real_name'];
	}
}

/**
 * get_username - look up the username from the member cache
 * Match smf_members by IP...  Imperfect, but close enough...
 *
 * Action: na - helper function
 *
 * @params inet $ip_packed
 *
 * @return string $username
 *
 */
function get_username($ip_packed) {
	global $member_cache;

	$name = 'Guest';
	$ip_hex = bin2hex($ip_packed);

	if (array_key_exists($ip_hex, $member_cache))
		$name = $member_cache[$ip_hex];

	return $name;
}

/**
 * get_request_type - simplify the request type for easy reporting
 *
 * Action: na - helper function
 *
 * @params string $request (from web access log)
 *
 * @return string $request_type
 *
 */
function get_request_type($request) {
	if (stripos($request, 'area=alerts_popup') !== false)
		$request_type = 'Alerts';
	elseif (stripos($request, 'type=rss') !== false)
		$request_type = 'RSS';
	elseif (stripos($request, 'action=admin') !== false)
		$request_type = 'admin';
	elseif (stripos($request, 'action=keepalive') !== false)
		$request_type = 'Keepalive';
	elseif (stripos($request, 'action=printpage') !== false)
		$request_type = 'Print';
	elseif (stripos($request, 'action=recent') !== false)
		$request_type = 'Recent';
	elseif (stripos($request, 'action=unread') !== false)
		$request_type = 'Unread';
	elseif (stripos($request, 'action=likes') !== false)
		$request_type = 'Likes';
	elseif (stripos($request, 'action=dlattach') !== false)
		$request_type = 'Attach';
	elseif (stripos($request, 'action=quotefast') !== false)
		$request_type = 'Quote';
	elseif (stripos($request, 'action=markasread') !== false)
		$request_type = 'MarkRead';
	elseif (stripos($request, 'action=quickmod2') !== false)
		$request_type = 'Modify';
	elseif (stripos($request, 'action=profile') !== false)
		$request_type = 'Profile';
	elseif (stripos($request, 'action=pm') !== false)
		$request_type = 'PM';
	elseif (stripos($request, 'action=xml') !== false)
		$request_type = 'xml';
	elseif (stripos($request, 'action=.xml') !== false)
		$request_type = 'xml';
	elseif (stripos($request, 'action=attbr') !== false)
		$request_type = 'Attachment Browser';
	elseif (stripos($request, 'action=search') !== false)
		$request_type = 'Search';
	elseif (stripos($request, 'action=signup') !== false)
		$request_type = 'Signup';
	elseif (stripos($request, 'action=register') !== false)
		$request_type = 'Signup';
	elseif (stripos($request, 'action=join') !== false)
		$request_type = 'Signup';
	elseif (stripos($request, 'action=login') !== false)
		$request_type = 'Login';
	elseif (stripos($request, 'action=logout') !== false)
		$request_type = 'Logout';
	elseif (stripos($request, 'action=verificationcode') !== false)
		$request_type = 'Login';
	elseif (stripos($request, '.msg') !== false)
		$request_type = 'Message';
	elseif (stripos($request, 'msg=') !== false)
		$request_type = 'Message';
	elseif (stripos($request, 'topic=') !== false)
		$request_type = 'Topic';
	elseif (stripos($request, 'board=') !== false)
		$request_type = 'Board';
	elseif (stripos($request, ';wwwRedirect') !== false)
		$request_type = 'Redirect';
	elseif (stripos($request, '/smf/custom_avatar') !== false)
		$request_type = 'Avatar';
	elseif (stripos($request, '/smf/cron.php?ts=') !== false)
		$request_type = 'Cron';
	elseif (stripos($request, '/smf/index.php ') !== false)
		$request_type = 'Board Index';
	elseif (stripos($request, '/smf/proxy.php') !== false)
		$request_type = 'Proxy';
	elseif (stripos($request, '/smf/avatars') !== false)
		$request_type = 'Avatar';
	elseif (stripos($request, '/smf/Smileys') !== false)
		$request_type = 'Smileys';
	elseif (stripos($request, '/smf/Themes') !== false)
		$request_type = 'Theme';
	elseif (stripos($request, '/favicon.ico') !== false)
		$request_type = 'Favicon';
	elseif (stripos($request, '/robots.txt') !== false)
		$request_type = 'robots.txt';
	elseif (stripos($request, '/sitemap') !== false)
		$request_type = 'Sitemap';
	elseif (stripos($request, '/phpmyadmin') !== false)
		$request_type = 'Admin';
	elseif (stripos($request, '/admin') !== false)
		$request_type = 'Admin';
	else
		$request_type = 'Other';

	return $request_type;
}

/**
 * get_agent - simplify the agent for easy reporting
 *
 * Action: na - helper function
 *
 * @params string $useragent (from web access log)
 *
 * @return string $agent
 *
 */
function get_agent($useragent) {
	if ($useragent === '-')
		$agent = 'BLANK';
	elseif (stripos($useragent, '2ip bot') !== false)
		$agent = '2ip bot';
	elseif (stripos($useragent, '360Spider') !== false)
		$agent = '360Spider';
	elseif (stripos($useragent, 'AdsBot-Google') !== false)
		$agent = 'AdsBot-Google';
	elseif (stripos($useragent, 'AhrefsBot') !== false)
		$agent = 'AhrefsBot';
	elseif (stripos($useragent, 'AliyunSecBot') !== false)
		$agent = 'AliyunSecBot';
	elseif (stripos($useragent, 'Awario') !== false)
		$agent = 'Awario';
	elseif (stripos($useragent, 'amazonbot') !== false)
		$agent = 'amazonbot';
	elseif (stripos($useragent, 'applebot') !== false)
		$agent = 'applebot';
	elseif (stripos($useragent, 'ArchiveBot') !== false)
		$agent = 'ArchiveBot';
	elseif (stripos($useragent, 'BaiduSpider') !== false)
		$agent = 'BaiduSpider';
	elseif (stripos($useragent, 'bingbot') !== false)
		$agent = 'bingbot';
	elseif (stripos($useragent, 'BLEXBot') !== false)
		$agent = 'BLEXBot';
	elseif (stripos($useragent, 'Bravebot') !== false)
		$agent = 'Bravebot';
	elseif (stripos($useragent, 'Bytespider') !== false)
		$agent = 'Bytespider';
	elseif (stripos($useragent, 'Cincraw') !== false)
		$agent = 'Cincraw';
	elseif (stripos($useragent, 'claudebot') !== false)
		$agent = 'claudebot';
	elseif (stripos($useragent, 'coccocbot') !== false)
		$agent = 'coccocbot';
	elseif (stripos($useragent, 'commoncrawl') !== false)
		$agent = 'commoncrawl';
	elseif (stripos($useragent, 'dataforseo-bot') !== false)
		$agent = 'dataforseo-bot';
	elseif (stripos($useragent, 'Discordbot') !== false)
		$agent = 'Discordbot';
	elseif (stripos($useragent, 'DomainStatsBot') !== false)
		$agent = 'DomainStatsBot';
	elseif (stripos($useragent, 'DotBot') !== false)
		$agent = 'DotBot';
	elseif (stripos($useragent, 'DuckAssistBot') !== false)
		$agent = 'DuckAssistBot';
	elseif (stripos($useragent, 'duckduckbot') !== false)
		$agent = 'duckduckbot';
	elseif (stripos($useragent, 'DuckDuckGo-Favicons-Bot') !== false)
		$agent = 'DuckDuckGo-Favicons-Bot';
	elseif (stripos($useragent, 'facebookexternalhit') !== false)
		$agent = 'facebookexternalhit';
	elseif (stripos($useragent, 'Gaisbot') !== false)
		$agent = 'Gaisbot';
	elseif (stripos($useragent, 'Googlebot') !== false)
		$agent = 'Googlebot';
	elseif (stripos($useragent, 'GoogleOther') !== false)
		$agent = 'GoogleOther';
	elseif (stripos($useragent, 'google.com/bot') !== false)
		$agent = 'google.com/bot';
	elseif (stripos($useragent, 'HawaiiBot') !== false)
		$agent = 'HawaiiBot';
	elseif (stripos($useragent, 'iAskBot') !== false)
		$agent = 'iAskBot';
	elseif (stripos($useragent, 'keys-so-bot') !== false)
		$agent = 'keys-so-bot';
	elseif (stripos($useragent, 'LinerBot') !== false)
		$agent = 'LinerBot';
	elseif (stripos($useragent, 'meta-externalagent') !== false)
		$agent = 'meta-externalagent';
	elseif (stripos($useragent, 'MixrankBot') !== false)
		$agent = 'MixrankBot';
	elseif (stripos($useragent, 'mj12bot') !== false)
		$agent = 'mj12bot';
	elseif (stripos($useragent, 'MojeekBot') !== false)
		$agent = 'MojeekBot';
	elseif (stripos($useragent, 'msnbot') !== false)
		$agent = 'msnbot';
	elseif (stripos($useragent, 'openai') !== false)
		$agent = 'openai';
	elseif (stripos($useragent, 'petalbot') !== false)
		$agent = 'petalbot';
	elseif (stripos($useragent, 'Pinterestbot') !== false)
		$agent = 'Pinterestbot';
	elseif (stripos($useragent, 'python-requests') !== false)
		$agent = 'python-requests';
	elseif (stripos($useragent, 'Qwantbot') !== false)
		$agent = 'Qwantbot';
	elseif (stripos($useragent, 'redditbot') !== false)
		$agent = 'redditbot';
	elseif (stripos($useragent, 'RU_Bot') !== false)
		$agent = 'RU_Bot';
	elseif (stripos($useragent, 'Screaming Frog SEO Spider') !== false)
		$agent = 'Screaming Frog SEO Spider';
	elseif (stripos($useragent, 'SeekportBot') !== false)
		$agent = 'SeekportBot';
	elseif (stripos($useragent, 'SemrushBot') !== false)
		$agent = 'SemrushBot';
	elseif (stripos($useragent, 'seznambot') !== false)
		$agent = 'seznambot';
	elseif (stripos($useragent, 'SiteLockSpider') !== false)
		$agent = 'SiteLockSpider';
	elseif (stripos($useragent, 'Slack-ImgProxy') !== false)
		$agent = 'Slack-ImgProxy';
	elseif (stripos($useragent, 'Sogou') !== false)
		$agent = 'Sogou';
	elseif (stripos($useragent, 'StartmeBot') !== false)
		$agent = 'StartmeBot';
	elseif (stripos($useragent, 'SuperBot') !== false)
		$agent = 'SuperBot';
	elseif (stripos($useragent, 'TelegramBot') !== false)
		$agent = 'TelegramBot';
	elseif (stripos($useragent, 'Thinkbot') !== false)
		$agent = 'Thinkbot';
	elseif (stripos($useragent, 'TikTokSpider') !== false)
		$agent = 'TikTokSpider';
	elseif (stripos($useragent, 'trendictionbot') !== false)
		$agent = 'trendictionbot';
	elseif (stripos($useragent, 'Twitterbot') !== false)
		$agent = 'Twitterbot';
	elseif (stripos($useragent, 'TurnitinBot') !== false)
		$agent = 'TurnitinBot';
	elseif (stripos($useragent, 'WellKnownBot') !== false)
		$agent = 'WellKnownBot';
	elseif (stripos($useragent, 'WireReaderBot') !== false)
		$agent = 'WireReaderBot';
	elseif (stripos($useragent, 'wpbot') !== false)
		$agent = 'wpbot';
	elseif (stripos($useragent, 'yacybot') !== false)
		$agent = 'yacybot';
	elseif (stripos($useragent, 'yandex') !== false)
		$agent = 'yandex';
	elseif (stripos($useragent, 'YisouSpider') !== false)
		$agent = 'YisouSpider';
	elseif (stripos($useragent, 'ZoomBot') !== false)
		$agent = 'ZoomBot';
	elseif (stripos($useragent, 'zoominfobot') !== false)
		$agent = 'zoominfobot';
	elseif (stripos($useragent, 'spider') !== false)
		$agent = 'Other bot';
	elseif (stripos($useragent, 'bot') !== false)
		$agent = 'Other bot';
	elseif (stripos($useragent, 'crawl') !== false)
		$agent = 'Other bot';
	else
		$agent = 'User';

	return $agent;
}

/**
 * get_browser_ver - simplify the browser version for easy reporting
 *
 * Action: na - helper function
 *
 * @params string $useragent (from web access log)
 *
 * @return string $browser_ver
 *
 */
function get_browser_ver($useragent) {
	$browser_ver = '';
	$matches = array();

	// Gets most browser versions here...
	static $pattern1 = '~(?:firefox|chrome|msie|safari|edg|edga|edgios|opera|vivaldi)\/\d{1,3}\b~i';
	if (preg_match($pattern1, $useragent, $matches))
		$browser_ver = $matches[0];

	// Second swipe at it, lots of iphones use this
	static $pattern2 = '~(?:mobile)\/\d\d[a-z]\d\d\d\b~i';
	if (empty($browser_ver))
		if (preg_match($pattern2, $useragent, $matches))
			$browser_ver = $matches[0];

	// Third swipe at it, lots of iphones use this long version of a safari version
	static $pattern3 = '~(?:safari)\/\d{4,5}\b~i';
	if (empty($browser_ver))
		if (preg_match($pattern3, $useragent, $matches))
			$browser_ver = $matches[0];

	return $browser_ver;
}
