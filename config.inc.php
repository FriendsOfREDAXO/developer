<?php

$mypage = 'developer';

$REX['ADDON']['rxid'][$mypage] = '338';
$REX['ADDON']['page'][$mypage] = $mypage;

if ($REX['REDAXO']) {
  $I18N->appendFile($REX['INCLUDE_PATH'].'/addons/'.$mypage.'/lang/');
  $REX['ADDON']['name'][$mypage] = $I18N->msg('developer_name');
}

$REX['ADDON']['perm'][$mypage] = "admin[]";
$REX['ADDON']['author'][$mypage] = 'Gregor Harlan';
$REX['ADDON']['version'][$mypage] = '2.1';

// --- DYN
$REX['ADDON']['developer']['config']['templates'] = "1";
$REX['ADDON']['developer']['config']['modules'] = "1";
$REX['ADDON']['developer']['config']['dir'] = "developer_files";
// --- /DYN

if ($REX['ADDON']['developer']['config']['templates'] || $REX['ADDON']['developer']['config']['modules'])
{
  $loggedIn = true;
  if (!isset($REX['LOGIN']) || !$REX['LOGIN'])
  {
    $REX['LOGIN'] = new rex_backend_login($REX['TABLE_PREFIX'] .'user');
    $loggedIn = $REX['LOGIN']->checkLogin();
  }
  if ($loggedIn && $REX['LOGIN']->USER->isAdmin())
  {
    require_once $REX['INCLUDE_PATH'] .'/addons/developer/classes/class.rex_developer_manager.inc.php';
    rex_developer_manager::sync();
  }
}