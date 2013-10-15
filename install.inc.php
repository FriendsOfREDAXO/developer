<?php

$dir = dirname(__FILE__);
$I18N->appendFile($dir . '/lang/');
$msg = '';

$minPhpVersion = '5.3.3';
$minRexVersion = '4.3.2';
if (version_compare(PHP_VERSION, $minPhpVersion) < 0) {
    $msg = $I18N->msg('developer_install_php_version', $minPhpVersion);
} elseif (version_compare($REX['VERSION'] . '.' . $REX['SUBVERSION'] . '.' . $REX['MINORVERSION'], $minRexVersion) < 0) {
    $msg = $I18N->msg('developer_install_rex_version', $minRexVersion);
} else {
    require_once $dir . '/lib/manager.php';
    $msg = rex_developer_manager::checkDir('data/addons/developer');
}

if ($msg != '') {
    $REX['ADDON']['installmsg']['developer'] = $msg;
    $REX['ADDON']['install']['developer'] = false;
} else {
    $REX['ADDON']['install']['developer'] = true;
}
