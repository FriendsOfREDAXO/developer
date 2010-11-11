<?php

$REX['ADDON']['install']['developer'] = 0;

require_once dirname(__FILE__) .'/classes/class.rex_developer_manager.inc.php';
rex_developer_manager::deleteDir($REX['ADDON']['settings']['developer']['dir']);