<?php
/**
 *	DB interaction for the Web Access Log Analyzer mod for SMF..
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
 * start_transaction
 *
 * @return null
 *
 */
function start_transaction() {
	global $smcFunc;

	$smcFunc['db_transaction']('begin');
}

/**
 * commit
 *
 * @return null
 *
 */
function commit() {
	global $smcFunc;

	$smcFunc['db_transaction']('commit');
}

/**
 * truncate_dbip_asn
 *
 * @return null
 *
 */
function truncate_dbip_asn() {
	global $smcFunc;

	$smcFunc['db_query']('', 'TRUNCATE {db_prefix}wala_dbip_asn',
		array()
	);
	// Reflect status...
	update_status('asn');
}

/**
 * truncate_dbip_country
 *
 * @return null
 *
 */
function truncate_dbip_country() {
	global $smcFunc;

	$smcFunc['db_query']('', 'TRUNCATE {db_prefix}wala_dbip_country',
		array()
	);
	// Reflect status...
	update_status('country');
}

/**
 * truncate_members
 *
 * @return null
 *
 */
function truncate_members() {
	global $smcFunc;

	$smcFunc['db_query']('', 'TRUNCATE {db_prefix}wala_members',
		array()
	);
	// Reflect status...
	update_status('member');
}

/**
 * truncate_web_access_log
 *
 * @return null
 *
 */
function truncate_web_access_log() {
	global $smcFunc;

	$smcFunc['db_query']('', 'TRUNCATE {db_prefix}wala_web_access_log',
		array()
	);
	// Reflect status...
	update_status('log');
}

/**
 * insert_dbip_asn
 *
 * @param array $inserts
 *
 * @return null
 *
 */
function insert_dbip_asn(&$inserts) {
	global $smcFunc, $modSettings;

	if (empty($inserts))
		return;

	// Temporarily disable query check...  Takes a MASSIVE amount of time on large inserts...
	$modSettings['disableQueryCheck'] = '1';

	$smcFunc['db_insert']('insert',
		'{db_prefix}wala_dbip_asn',
		array('ip_from_packed' => 'inet', 'ip_to_packed' => 'inet', 'ip_from' => 'string-42', 'ip_to' => 'string-42', 'asn' => 'string-10', 'asn_name' => 'string-255'),
		$inserts,
		array('ip_to_packed'),
	);
}

/**
 * insert_dbip_country
 *
 * @param array $inserts
 *
 * @return null
 *
 */
function insert_dbip_country(&$inserts) {
	global $smcFunc, $modSettings;

	if (empty($inserts))
		return;

	// Temporarily disable query check...  Takes a MASSIVE amount of time on large inserts...
	$modSettings['disableQueryCheck'] = '1';

	$smcFunc['db_insert']('insert',
		'{db_prefix}wala_dbip_country',
		array('ip_from_packed' => 'inet', 'ip_to_packed' => 'inet', 'ip_from' => 'string-42', 'ip_to' => 'string-42', 'country' => 'string-2'),
		$inserts,
		array('ip_to_packed'),
	);
}

/**
 * insert_log
 *
 * @param array $inserts
 *
 * @return null
 *
 */
function insert_log(&$inserts) {
	global $smcFunc, $modSettings;

	if (empty($inserts))
		return;

	// Temporarily disable query check...  Takes a MASSIVE amount of time on large inserts...
	$modSettings['disableQueryCheck'] = '1';

	$smcFunc['db_insert']('insert',
		'{db_prefix}wala_web_access_log',
		array('ip_packed' => 'inet', 'client' => 'string-10', 'requestor' => 'string-10', 'raw_datetime' => 'string-32', 'raw_tz' => 'string-6', 'request' => 'string-255', 'status' => 'int', 'size' => 'int', 'referrer' => 'string-255', 'useragent' => 'string-255', 'ip_disp' => 'string-42', 'request_type' => 'string-15', 'agent' => 'string-25', 'browser_ver' => 'string-25', 'datetime' => 'int'),
		$inserts,
		array('id_entry'),
	);
}

/**
 * get_asns
 *
 * @params inet min IP
 * @params inet max IP
 *
 * @return array result
 *
 */
function get_asns($min_ip_packed, $max_ip_packed) {
	global $smcFunc, $db_type;

	$min_hex = bin2hex($min_ip_packed);
	$min_length = strlen($min_ip_packed);
	$min_disp = inet_ntop($min_ip_packed);
	$max_hex = bin2hex($max_ip_packed);
	$max_length = strlen($max_ip_packed);
	$max_disp = inet_ntop($max_ip_packed);

	if ($db_type == 'postgresql')
		$sql = 'SELECT ip_from_packed, ip_to_packed, asn FROM {db_prefix}wala_dbip_asn WHERE ip_to_packed >= \'' . $min_disp. '\' AND ip_from_packed <= \'' . $max_disp . '\' ORDER BY ip_from_packed';
	else {
		if ($min_length == $max_length) {
			$sql = 'SELECT ip_from_packed, ip_to_packed, asn FROM {db_prefix}wala_dbip_asn WHERE ip_to_packed >= UNHEX(\'' . $min_hex . '\') AND ip_from_packed <= UNHEX(\'' . $max_hex . '\') AND LENGTH(ip_from_packed) = ' . $max_length . ' ORDER BY ip_from_packed';
		}
		else {
			// mixed ipv4 & ipv6
			$sql = 'SELECT ip_from_packed, ip_to_packed, asn FROM {db_prefix}wala_dbip_asn WHERE (ip_to_packed >= UNHEX(\'' . $min_hex . '\') && (LENGTH(ip_from_packed) = ' . $min_length. ')) OR (ip_from_packed <=  UNHEX(\'' . $max_hex . '\') && (LENGTH(ip_to_packed) = ' . $max_length . ')) ORDER BY LENGTH(ip_from_packed), ip_from_packed';
		}
	}
	$result = $smcFunc['db_query']('', $sql);

	// Under SMF, PG & MySQL behave differently with inet types.  MySQL reads binary, but wants a display upon insert.  
	// PG always reads & writes display.
	// WALA uses binary on reads, so needs to xlate pg on reads here.
	$all_rows = array();
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		if ($db_type == 'postgresql') {
			$row['ip_from_packed'] = inet_pton($row['ip_from_packed']);
			$row['ip_to_packed'] = inet_pton($row['ip_to_packed']);
		}
		$all_rows[] = $row;
	}
	return $all_rows;
}

/**
 * get_countries
 *
 * @params inet min IP
 * @params inet max IP
 *
 * @return array result
 *
 */
function get_countries($min_ip_packed, $max_ip_packed) {
	global $smcFunc, $db_type;

	$min_hex = bin2hex($min_ip_packed);
	$min_length = strlen($min_ip_packed);
	$min_disp = inet_ntop($min_ip_packed);
	$max_hex = bin2hex($max_ip_packed);
	$max_length = strlen($max_ip_packed);
	$max_disp = inet_ntop($max_ip_packed);

	if ($db_type == 'postgresql')
		$sql = 'SELECT ip_from_packed, ip_to_packed, country FROM {db_prefix}wala_dbip_country WHERE ip_to_packed >= \'' . $min_disp. '\' AND ip_from_packed <= \'' . $max_disp . '\' ORDER BY ip_from_packed';
	else {
		if ($min_length == $max_length) {
			$sql = 'SELECT ip_from_packed, ip_to_packed, country FROM {db_prefix}wala_dbip_country WHERE ip_to_packed >= UNHEX(\'' . $min_hex . '\') AND ip_from_packed <= UNHEX(\'' . $max_hex . '\') AND LENGTH(ip_from_packed) = ' . $max_length . ' ORDER BY ip_from_packed';
		}
		else {
			// mixed ipv4 & ipv6
			$sql = 'SELECT ip_from_packed, ip_to_packed, country FROM {db_prefix}wala_dbip_country WHERE (ip_to_packed >= UNHEX(\'' . $min_hex . '\') && (LENGTH(ip_from_packed) = ' . $min_length. ')) OR (ip_from_packed <=  UNHEX(\'' . $max_hex . '\') && (LENGTH(ip_to_packed) = ' . $max_length . ')) ORDER BY LENGTH(ip_from_packed), ip_from_packed';
		}
	}
	$result = $smcFunc['db_query']('', $sql);

	// Under SMF, PG & MySQL behave differently with inet types.  MySQL reads binary, but wants a display upon insert.  
	// PG always reads & writes display.
	// WALA uses binary on reads, so needs to xlate pg on reads here.
	$all_rows = array();
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		if ($db_type == 'postgresql') {
			$row['ip_from_packed'] = inet_pton($row['ip_from_packed']);
			$row['ip_to_packed'] = inet_pton($row['ip_to_packed']);
		}
		$all_rows[] = $row;
	}
	return $all_rows;
}

/**
 * count_smf_members
 *
 * @return int
 *
 */
function count_smf_members() {
	global $smcFunc;
	$rec_count = 0;

	$result = $smcFunc['db_query']('', 'SELECT COUNT(*) AS reccount FROM {db_prefix}members');
	$rec_count = (int) $smcFunc['db_fetch_assoc']($result)['reccount'];
	return $rec_count;
}

/**
 * count_web_access_log
 *
 * @return int
 *
 */
function count_web_access_log() {
	global $smcFunc;
	$rec_count = 0;

	$result = $smcFunc['db_query']('', 'SELECT COUNT(*) AS reccount FROM {db_prefix}wala_web_access_log');
	$rec_count = $smcFunc['db_fetch_assoc']($result)['reccount'];
	return $rec_count;
}

/**
 * get_smf_members - read a chunk of members from smf_members to load to reporting db
 *
 * @params int offset
 * @params int limit
 *
 * @return array
 *
 */
function get_smf_members($offset = 0, $limit = 50000) {
	global $smcFunc, $db_type;

	$result = $smcFunc['db_query']('', 'SELECT member_ip, id_member, real_name, is_activated , posts, total_time_logged_in, date_registered, last_login FROM {db_prefix}members ORDER BY id_member ASC LIMIT ' . $limit . ' OFFSET ' . $offset);

	// Under SMF, PG & MySQL behave differently with inet types.  MySQL reads binary, but wants a display upon insert.  
	// PG always reads & writes display.
	// We need display, for this member load, so pg is ok.
	$all_rows = array();
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		if ($db_type == 'mysql') {
			$row['member_ip'] = inet_ntop($row['member_ip']);
		}
		$all_rows[] = $row;
	}
	return $all_rows;
}

/**
 * insert_members - load a chunk of members to reporting db
 *
 * @params array $inserts
 *
 * @return null
 *
 */
function insert_members(&$inserts) {
	global $smcFunc, $modSettings;

	if (empty($inserts))
		return;

	// Temporarily disable query check...  Takes a MASSIVE amount of time on large inserts...
	$modSettings['disableQueryCheck'] = '1';

	$smcFunc['db_insert']('insert',
		'{db_prefix}wala_members',
		array('ip_packed' => 'inet', 'id_member' => 'int', 'real_name' => 'string-255', 'is_activated' => 'int', 'posts' => 'int', 'total_time_logged_in' => 'int', 'date_registered' => 'int', 'last_login' => 'int'),
		$inserts,
		array('id_member'),
	);
}

/**
 * get_member_ips - load member IPs & names from reporting db
 *
 * @return array
 *
 */
function get_member_ips() {
	global $smcFunc, $db_type;

	if ($db_type == 'postgresql')
		$sql = 'SELECT ip_packed, real_name FROM {db_prefix}wala_members ORDER BY ip_packed ASC';
	else
		$sql = 'SELECT ip_packed, real_name FROM {db_prefix}wala_members ORDER BY LENGTH(ip_packed), ip_packed ASC';

	$result = $smcFunc['db_query']('', $sql);

	// Under SMF, PG & MySQL behave differently with inet types.  MySQL reads binary, but wants a display upon insert.
	// PG always reads & writes display.
	// WALA uses binary on reads, so needs to xlate pg on reads here.
	$all_rows = array();
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		if ($db_type == 'postgresql') {
			$row['ip_packed'] = inet_pton($row['ip_packed']);
		}
		$all_rows[] = $row;
	}
	return $all_rows;
}

/**
 * load_asn_names - load unique asns & names
 *
 * @return null
 *
 */
function load_asn_names() {
	global $smcFunc, $modSettings;

	$smcFunc['db_query']('', 'TRUNCATE {db_prefix}wala_asns',
		array()
	);

	$result = $smcFunc['db_query']('', 'SELECT DISTINCT(asn), asn_name FROM {db_prefix}wala_dbip_asn ORDER BY asn');
	$inserts = $smcFunc['db_fetch_all']($result);

	// Temporarily disable query check...  Takes a MASSIVE amount of time on large inserts...
	$modSettings['disableQueryCheck'] = '1';

	$smcFunc['db_insert']('insert',
		'{db_prefix}wala_asns',
		array('asn' => 'string-10', 'asn_name' => 'string-255'),
		$inserts,
		array('asn'),
	);
}

/**
 * get_status - get all status info about uploads
 *
 * @return array
 *
 */
function get_status() {
	global $smcFunc;

	$result = $smcFunc['db_query']('', 'SELECT * FROM {db_prefix}wala_status');
	$all_rows = $smcFunc['db_fetch_all']($result);
	return $all_rows;
}

/**
 * update_status
 *
 * @params string file_type
 * @params string file_name
 * @params int datetime
 *
 * @return null
 *
 */
function update_status($file_type, $file_name = '', $last_proc_time = 0) {
	global $smcFunc;

	$smcFunc['db_insert']('replace',
		'{db_prefix}wala_status',
		array('file_type' => 'string-10', 'file_name' => 'string-255', 'last_proc_time' => 'int'),
		array(array($file_type, $file_name, $last_proc_time)),
		array('file_type'),
	);
}

/**
 * wala_report_request
 * Does some simple xlation of MySQL syntax to Postgresql
 *
 * @params string sql
 *
 * @return array
 *
 */
function wala_report_request($sql = '') {
	global $smcFunc, $db_type;

	if (empty($sql))
		return array();

	if ($db_type == 'postgresql')
		$sql = strtr($sql, array(
				'FROM_UNIXTIME' => 'TO_TIMESTAMP',
			)
		);

	$result = $smcFunc['db_query']('', $sql);
	$all_rows = $smcFunc['db_fetch_all']($result);
	return $all_rows;
}

/**
 * get_wala_members
 *
 * @params int offset
 * @params int limit
 *
 * @return array
 *
 */
function get_wala_members($offset, $limit) {
	global $smcFunc, $db_type;

	// pg properly sorts ip with ipv4 first, ipv6 next... mysql doesn't, and we don't want ipv6 & ipv4 all mixed together...
	if ($db_type == 'postgresql')
		$sql = 'SELECT ip_packed, id_member FROM {db_prefix}wala_members ORDER BY ip_packed, id_member LIMIT ' . $limit . ' OFFSET ' .$offset;
	else
		$sql = 'SELECT ip_packed, id_member FROM {db_prefix}wala_members ORDER BY LENGTH(ip_packed), ip_packed, id_member LIMIT ' . $limit . ' OFFSET ' .$offset;

	$result = $smcFunc['db_query']('', $sql);
	$all_rows = array();
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		if ($db_type == 'postgresql') {
			$row['ip_packed'] = inet_pton($row['ip_packed']);
		}
		$all_rows[] = $row;
	}
	return $all_rows;
}

/**
 * update_wala_members
 *
 * @params array $member_info
 *
 */
function update_wala_members($member_info) {
	global $smcFunc, $db_type;

	$sql = 'UPDATE {db_prefix}wala_members SET asn = \'' . $member_info['asn'] . '\', country = \'' . $member_info['country'] . '\' WHERE id_member = ' . $member_info['id_member'];
	$result = $smcFunc['db_query']('', $sql);
}

/**
 * get_web_access_log
 *
 * @params int offset
 * @params int limit
 *
 * @return array
 *
 */
function get_web_access_log($offset, $limit) {
	global $smcFunc, $db_type;

	// pg properly sorts ip with ipv4 first, ipv6 next... mysql doesn't, and we don't want ipv6 & ipv4 all mixed together...
	if ($db_type == 'postgresql')
		$sql = 'SELECT ip_packed, id_entry FROM {db_prefix}wala_web_access_log ORDER BY ip_packed, id_entry LIMIT ' . $limit . ' OFFSET ' .$offset;
	else
		$sql = 'SELECT ip_packed, id_entry FROM {db_prefix}wala_web_access_log ORDER BY LENGTH(ip_packed), ip_packed, id_entry LIMIT ' . $limit . ' OFFSET ' .$offset;

	$result = $smcFunc['db_query']('', $sql);
	$all_rows = array();
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		if ($db_type == 'postgresql') {
			$row['ip_packed'] = inet_pton($row['ip_packed']);
		}
		$all_rows[] = $row;
	}
 	return $all_rows;
}

/**
 * update_web_access_log
 *
 * @params array $log_info
 *
 */
function update_web_access_log($log_info) {
	global $smcFunc, $db_type;

	$sql = 'UPDATE {db_prefix}wala_web_access_log SET asn = \'' . $log_info['asn'] . '\', country = \'' . $log_info['country'] . '\', username = \'' . $log_info['username'] . '\' WHERE id_entry = ' . $log_info['id_entry'];
	$result = $smcFunc['db_query']('', $sql);
}
