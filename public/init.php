<?php
use APISubiektGT\Logger;

$config = rand(0, 1);
if ($config == 0) DEFINE('CONFIG_INI_FILE',dirname(__FILE__).'/../config/api-subiekt-gt.ini');
	else DEFINE('CONFIG_INI_FILE',dirname(__FILE__).'/../config/api-subiekt-gt2.ini');

DEFINE('LOG_DIR',dirname(__FILE__).'/../log/');

include_once(dirname(__FILE__).'/../src/autoload.php');
Logger::getInstance(LOG_DIR);


?>