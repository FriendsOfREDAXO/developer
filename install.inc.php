<?php

$dir = dirname(__FILE__);
$I18N->appendFile($dir . '/lang/');
$msg = '';

$minPhpVersion = '5.3.3';
if (version_compare(PHP_VERSION, $minPhpVersion) < 0) {

  $msg = $I18N->msg('developer_install_php_version', $minPhpVersion);

} else {

  if (!@is_writeable($dir . '/settings.inc.php')) {
    $msg = $I18N->msg('developer_install_perm_settings');
  } else {
    require_once $dir . '/settings.inc.php';
    require_once $dir . '/lib/manager.php';
    $msg = rex_developer_manager::checkDir($REX['ADDON']['settings']['developer']['dir']);
  }

}

if ($msg != '') {
  $REX['ADDON']['installmsg']['developer'] = $msg;
  $REX['ADDON']['install']['developer'] = false;
} else {
  $REX['ADDON']['install']['developer'] = true;
}
