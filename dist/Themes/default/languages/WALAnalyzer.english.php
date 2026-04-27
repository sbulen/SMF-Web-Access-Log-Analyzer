<?php
// Version: 2.1.7; WALAnalyzer

// Menu
$txt['wala_title'] = 'Web Access Log Analyzer';
$txt['wala_desc_load'] = 'Tool to help analyze your Apache web access log files.  Process:<br> 1. Upload the DBIP ASN lite .csv file (found <a href=" https://db-ip.com/db/download/ip-to-asn-lite" target="_blank">here</a>)<br> 2. Upload the DBIP Country lite .csv file (found <a href="https://db-ip.com/db/download/ip-to-country-lite" target="_blank">here</a>)<br> 3. Load the member attribution used by the reports<br> 4. Upload a day\'s worth of logs.  It is suggested you keep the log less than 1M rows.<br><br>These steps must done in sequence, one at a time.  Each upload overwrites the previous content.  Once everything has been loaded, you can run reports on the WALA Reports tab.<br><br>Uploading the .gz compressed versions of the .csv files is preferred due to the smaller file sizes.  The first nine columns of the web access log must be in the Apache Combined Log Format.<br><br>The uploads, imports & attribution assignments take time.  If you navigate away from this screen while an upload is in progress, you will need to restart that upload.';
$txt['wala_desc_reports'] = 'Reports to help analyze your Apache web access log files.  Notes:<br> - On user-based reports, \'Guest\' means no user with a matching IP was found.<br> - On useragent-based reports, \'User\' means the useragent did not match any known/likely agent.<br> - \'Blocked\' vs \'Unblocked\' - \'Blocked\' includes only http status codes 403 & 429.  \'Unblocked\' excludes 403 and 429.';
$txt['wala_desc_download'] = 'Downloads a text file that contains a list of CIDRs.  Enter a comma-delimited set of two-character country codes and/or numeric ASNs to download.  A text command may also be provided, which would be added to the start of each line, e.g., if the intent is build a set of \'Deny From\' strings for use in .htaccess.<br><br>This is a lot of data...  Give it time...  If system resources are exceeded, large requests may need to be broken up into smaller pieces.';

$txt['wala_load'] = 'WALA Load';
$txt['wala_reports'] = 'WALA Reports';
$txt['wala_download'] = 'WALA Download';

// Text & button labels
$txt['wala_file_asn'] = 'DBIP ASN Definitions';
$txt['wala_file_country'] = 'DBIP Country Definitions';
$txt['wala_member_file'] = 'Load Member Attributes';
$txt['wala_access_log'] = 'Web Access Log';
$txt['wala_report_select'] = 'Select Report';
$txt['wala_reload'] = 'Load';
$txt['wala_updated'] = 'Updated';
$txt['wala_submit'] = 'Submit';
$txt['wala_download_request'] = 'Country codes & ASNs';
$txt['wala_download_prefix'] = 'Command';

// Errors
$txt['wala_file_error'] = 'Invalid file format importing WALA input';
$txt['wala_invalid_request'] = 'Invalid request';

// Processing status (all passed to js)
$txt['wala_loader'] = 'Wala File Loader';
$txt['wala_upprep'] = 'Preparing for upload...';
$txt['wala_uploaded'] = '% uploaded';
$txt['wala_prep'] = 'Preparing for import...';
$txt['wala_imported'] = '% imported';
$txt['wala_attribution'] = '% attribution';
$txt['wala_done'] = 'Import complete!';
$txt['wala_success'] = 'Success!';
$txt['wala_failed'] = 'File upload backend failure; check SMF, PHP & Apache logs';
$txt['wala_error_chunk'] = 'Error uploading chunk';

// Report titles
$txt['wala_rpt_reqsxcountryui'] = 'Requests by Country with User Info';
$txt['wala_rpt_reqsxasnui'] = 'Requests by ASN with User Info';
$txt['wala_rpt_reqsxagent'] = 'Requests by Useragent';
$txt['wala_rpt_reqsxuser'] = 'Requests by User';
$txt['wala_rpt_reqsxbrowser'] = 'Requests by Browser';
$txt['wala_rpt_ipsxcountry'] = 'Unique IPs by Country';
$txt['wala_rpt_ipsxasn'] = 'Unique IPs by ASN';
$txt['wala_rpt_likesxcountry'] = 'View Likes by Country';
$txt['wala_rpt_likesxasn'] = 'View Likes by ASN';

$txt['wala_rpt_userxcountry'] = 'Users by Country';
$txt['wala_rpt_userxasn'] = 'Users by ASN';
?>