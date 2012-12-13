<?php

$mypage = 'developer';

$REX['ADDON']['rxid'][$mypage] = '338';

if ($REX['REDAXO']) {
  $I18N->appendFile($REX['INCLUDE_PATH'] . '/addons/' . $mypage . '/lang/');
  $REX['ADDON']['name'][$mypage] = $I18N->msg('developer_name');
}

$REX['ADDON']['perm'][$mypage] = 'admin[]';
$REX['ADDON']['author'][$mypage] = 'Gregor Harlan';
$REX['ADDON']['version'][$mypage] = '3.0 dev';

require_once dirname(__FILE__) . '/settings.inc.php';

if (($REX['ADDON']['settings']['developer']['templates']
  || $REX['ADDON']['settings']['developer']['modules']
  || $REX['ADDON']['settings']['developer']['actions'])
  && (!$REX['REDAXO'] || is_object($REX['LOGIN']))
) {
  rex_register_extension('ADDONS_INCLUDED', 'rex_developer_start');

  function rex_developer_start($params)
  {
    global $REX, $I18N;
    if (session_id() == '')
      session_start();
    $loggedIn = isset($_SESSION[$REX['INSTNAME']]['UID']) && $_SESSION[$REX['INSTNAME']]['UID'] > 0;
    if ($loggedIn && (!isset($REX['LOGIN']) || !is_object($REX['LOGIN']))) {
      if (!is_object($I18N))
        $I18N = rex_create_lang($REX['LANG']);
      $REX['LOGIN'] = new rex_backend_login($REX['TABLE_PREFIX'] . 'user');
      $loggedIn = $REX['LOGIN']->checkLogin();
    }
    if ($loggedIn && $REX['LOGIN']->USER->isAdmin()) {
      require_once $REX['INCLUDE_PATH'] . '/addons/developer/classes/class.rex_developer_manager.inc.php';
      rex_developer_manager::sync();
    }
  }
}
