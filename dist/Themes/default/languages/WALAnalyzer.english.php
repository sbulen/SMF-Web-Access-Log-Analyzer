<?php
// Version: 2.1.6; WALAnalyzer

// Menu
$txt['wala_title'] = 'Web Access Log Analyzer';
$txt['wala_desc'] = 'Tool to help analyze your Apache web access log files.  Process:<br> 1. Upload the DBIP ASN lite .csv file<br> 2. Upload the DBIP Country lite .csv file<br> 3. Load the member attribution used by the reports<br> 4. Upload a day\'s worth of logs.  It is suggested you keep the log less than 1M rows.<br><br>These steps must done in sequence, one at a time.  Each upload overwrites the previous content.  Once everything has been loaded, you can run reports on the WALA Reports tab.<br><br>Uploading the .gz compressed versions of the .csv files is preferred due to the smaller file sizes.  The Apache log file must be in the Apache Combined Log Format.  Caching is strongly recommended.<br><br>The uploads, imports & attribution assignments take time.  If you navigate away from this screen while an upload is in progress, you will need to restart that upload.';
$txt['wala_desc_short'] = 'Reports to help analyze your Apache web access log files.  Notes:<br> - On user-based reports, \'Guest\' means no user with a matching IP was found.<br> - On useragent-based reports, \'User\' means the useragent did not match any known/likely agent.<br> - \'All\' vs \'Unblocked\' - \'Unblocked\' excludes http status codes 403 and 429.  \'All\' includes all http statuses.';

$txt['wala_load'] = 'WALA Load';
$txt['wala_reports'] = 'WALA Reports';

// Text & button labels
$txt['wala_file_asn'] = 'DBIP ASN Definitions';
$txt['wala_file_country'] = 'DBIP Country Definitions';
$txt['wala_member_file'] = 'Load Member Attributes';
$txt['wala_access_log'] = 'Web Access Log';
$txt['wala_report_select'] = 'Select Report';
$txt['wala_reload'] = 'Load';
$txt['wala_updated'] = 'Updated';
$txt['wala_submit'] = 'Submit';

// Errors
$txt['wala_file_error'] = 'Invalid file format importing WALA input';

// Processing status (all passed to js)
$txt['wala_loader'] = 'Wala File Loader';
$txt['wala_uploaded'] = '% uploaded';
$txt['wala_prep'] = 'Preparing for import...';
$txt['wala_imported'] = '% imported';
$txt['wala_attribution'] = '% attribution';
$txt['wala_done'] = 'Import complete!';
$txt['wala_success'] = 'Success!';
$txt['wala_failed'] = 'File upload backend failure; check SMF, PHP & Apache logs';
$txt['wala_error_chunk'] = 'Error uploading chunk';

// Report titles
$txt['wala_rpt_ureqsxcountryui'] = 'Unblocked Requests by Country with User Info';
$txt['wala_rpt_areqsxcountryui'] = 'All Requests by Country with User Info';
$txt['wala_rpt_ureqsxasnui'] = 'Unblocked Requests by ASN with User Info';
$txt['wala_rpt_areqsxasnui'] = 'All Requests by ASN with User Info';

$txt['wala_rpt_ureqsxagent'] = 'Unblocked Requests by Useragent';
$txt['wala_rpt_areqsxagent'] = 'All Requests by Useragent';

$txt['wala_rpt_ureqsxuser'] = 'Unblocked Requests by User';
$txt['wala_rpt_areqsxuser'] = 'All Requests by User';

$txt['wala_rpt_ureqsxbrowser'] = 'Unblocked Requests by Browser';
$txt['wala_rpt_areqsxbrowser'] = 'All Requests by Browser';

$txt['wala_rpt_uipsxcountry'] = 'Unblocked Unique IPs by Country';
$txt['wala_rpt_aipsxcountry'] = 'All Unique IPs by Country';
$txt['wala_rpt_uipsxasn'] = 'Unblocked Unique IPs by ASN';
$txt['wala_rpt_aipsxasn'] = 'All Unique IPs by ASN';

$txt['wala_rpt_ulikesxcountry'] = 'Unblocked View Likes by Country';
$txt['wala_rpt_alikesxcountry'] = 'All View Likes by Country';
$txt['wala_rpt_ulikesxasn'] = 'Unblocked View Likes by ASN';
$txt['wala_rpt_alikesxasn'] = 'All View Likes by ASN';

$txt['wala_rpt_userxcountry'] = 'Users by Country';
$txt['wala_rpt_userxasn'] = 'Users by ASN';

?>