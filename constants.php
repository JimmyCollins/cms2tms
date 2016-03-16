<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');

if(!defined('CMS_WITH_TMS_PLUGIN_URL')) {
  define('CMS_WITH_TMS_PLUGIN_URL', plugins_url() . '/' . basename(dirname(__FILE__)));
}

define('GS_PLUGIN_PATH', dirname(__FILE__));
define('GS_PLUGIN_FOLDER_NAME', basename(dirname(__FILE__)));
define('GS_WSDL_URL', plugins_url(GS_PLUGIN_FOLDER_NAME . '/AmbassadorWebService.xml'));
define('GS_CRON_FREQUENCY_NAME', 'every_minute');
define('GS_AUTOCONFIG_URL', 'http://json.cmswithtms.net/autoconfig.json');
//define('GS_AUTOCONFIG_URL', CMS_WITH_TMS_PLUGIN_URL . '/' . 'autoconfig.json');


?>
