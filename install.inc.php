<?php

$dir = dirname(__FILE__);
$I18N->appendFile($dir . '/lang/');
$msg = '';

if (version_compare(PHP_VERSION, '5.3.0') < 0) {

  $msg = $I18N->msg('developer_install_php_version');

} else {

  if (!@is_writeable($dir . '/settings.inc.php')) {
    $msg = $I18N->msg('developer_install_perm_settings');
  }

  require_once $dir . '/settings.inc.php';

  if ($REX['ADDON']['settings']['developer']['templates']
    || $REX['ADDON']['settings']['developer']['modules']
    || $REX['ADDON']['settings']['developer']['actions']
  ) {
    require_once $dir . '/classes/class.rex_developer_manager.inc.php';

    $msg .= rex_developer_manager::checkDir($REX['ADDON']['settings']['developer']['dir']);
  }

}

if ($msg != '') {
  $REX['ADDON']['installmsg']['developer'] = $msg;
  $REX['ADDON']['install']['developer'] = false;
} else {
  $REX['ADDON']['install']['developer'] = true;
}
