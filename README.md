# cms2tms

Slightly modified version of cms2tms WordPress to GlobalSight connector from http://cmswithtms.net/.

Modified to use getJobExportFiles during job export from GlobalSight, as opposed to getLocalizedDocuments as in original implementation.

Also removed usage of superglobal $_POST as a function argument (can't do this in PHP 5.4 or later, it will generate a Fatal error).
