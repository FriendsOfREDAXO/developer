<?php

$dir = dirname(__FILE__);

require_once $dir . '/settings.inc.php';
require_once $dir . '/classes/class.rex_developer_manager.inc.php';
rex_developer_manager::deleteDir($REX['ADDON']['settings']['developer']['dir']);

$REX['ADDON']['install']['developer'] = 0;
