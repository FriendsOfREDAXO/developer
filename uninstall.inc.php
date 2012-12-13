<?php

require_once __DIR__ . '/settings.inc.php';
require_once __DIR__ . '/classes/class.rex_developer_manager.inc.php';
rex_developer_manager::deleteDir($REX['ADDON']['settings']['developer']['dir']);

$REX['ADDON']['install']['developer'] = 0;
