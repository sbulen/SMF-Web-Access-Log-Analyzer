[b]Description:[/b]
WALA, the SMF Web Access Log Analyzer, is a very simple reporting database created from your Apache web access logs.

The goal is to provide insight into the source and nature of your web traffic.  It will inform you which countries and ASNs are hitting you the hardest, and how this aligns with your registered user base.

This uses a very simple load-report approach, intended for a point-in time analysis.  If things fall out of date, you just reload them.

Eleven standard reports are provided.  The database is simple enough that you can expand upon the reports yourself, writing your own queries.  Since all tables used in the report are separate from the SMF core tables, reporting & tweaking is safe and straightforward.  All you need is access to the tables directly within any query tool, such as adminer or phpmyadmin.

Although it would be best to perform this analysis on a test copy of your SMF forum, if one is not available, it is safe to run on your production site.

The first nine columns of the apache web access logs usually conform to the Apache Combined Log format (https://httpd.apache.org/docs/2.4/logs.html).  This tool only works if the first nine fields in the log match this format, and that they are in English (e.g., 'Aug').

***You should confirm your web access logs are in this format before installing this tool.***

This tool also requires use of two free databases, the DBIP lite databases, found here: https://db-ip.com/db/lite.php
 - DBIP - IP to Country Lite, the csv version, must be loaded
 - DBIP - IP to ASN Lite, the csv version, must be loaded

A special thanks to @live627 for excellent input on performance & how to speed things up.

[b]Process:[/b]
 1. The first step is to download the DBIP ASN & Country lite csv databases, and load them.
 2. Next, load your forum member info.  ASN & Country are assigned to members based on IP.
 3. Next, load your web access log.  In theory any size log, but in practice, you should probably keep the log less than 1M rows.  Member, ASN & Country are assigned to log entries based on IP.
 4. Run the reports, & create your own queries as needed.

[b]Features:[/b]
 - The javascript Fetch api is used to handle fast file transfers, even for very large files.
 - For speed of processing, there is also a simple btree used for lookups during loading.
 - Input files may be either .csv or .gzipped .csv, as typically downloaded.

[b]Limitations:[/b]
 - https is a hard requirement, due to the use of the fetch api.
 - Apache access log "Combined Log Format" only.  (LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-agent}i\"" combined)
 - DBIP ASN & Country lite lookups only.
 - The free versions of the DBIP databases are not kept as current & as detailed as the paid versions, of course...  They're a little old, and a little gappy, but acceptable for high-level analytics.
 - Member attribution, provider ASN and country, is not updated real-time into SMF.  This is meant to be an offline reporting tool, refreshed when a new analysis is needed.

[b]Releases:[/b]
 - v1.0.0 Initial Commit
 - v1.0.1 Removed unused caching code
 - v1.0.2 Transactions for bulk inserts
 - v1.0.3 Improve upload & import speed
 - v1.0.4 Tighten up some validations
 - v1.0.5 Simplify & consolidate reports
