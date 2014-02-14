<?php

$mypage = 'developer';

if ($REX['REDAXO']) {
    $I18N->appendFile(__DIR__ . '/lang/');
    $REX['ADDON']['name'][$mypage] = $I18N->msg('developer_name');

    $REX['ADDON']['pages'][$mypage] = array(
        array('', $I18N->msg('developer_settings')),
    );
}

$REX['ADDON']['perm'][$mypage] = 'admin[]';
$REX['ADDON']['author'][$mypage] = 'Gregor Harlan';
$REX['ADDON']['version'][$mypage] = '3.3.0';

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/lib/manager.php';
require_once __DIR__ . '/lib/synchronizer.php';
require_once __DIR__ . '/lib/synchronizer_default.php';
require_once __DIR__ . '/lib/synchronizer_item.php';

$REX['ADDON']['settings']['developer']['templates'] = '1';
$REX['ADDON']['settings']['developer']['modules'] = '1';
$REX['ADDON']['settings']['developer']['actions'] = '1';
$REX['ADDON']['settings']['developer']['rename'] = '1';
$REX['ADDON']['settings']['developer']['prefix'] = '0';
$REX['ADDON']['settings']['developer']['umlauts'] = '1';
$REX['ADDON']['settings']['developer']['delete'] = '1';
$REX['ADDON']['settings']['developer']['dir'] = 'data/addons/developer';

define('REX_DEVELOPER_SETTINGS_FILE', $REX['INCLUDE_PATH'] . '/data/addons/developer/settings.inc.php');
if (file_exists(REX_DEVELOPER_SETTINGS_FILE)) {
    require_once REX_DEVELOPER_SETTINGS_FILE;
}

if (!$REX['REDAXO'] || is_object($REX['LOGIN'])) {
    rex_register_extension('ADDONS_INCLUDED', function ($params) {
        global $REX, $I18N;
        if (is_callable('rex_login::startSession')) {
            rex_login::startSession();
        } else {
            if (session_id() == '') {
                session_start();
            }
        }
        $loggedIn = isset($_SESSION[$REX['INSTNAME']]['UID']) && $_SESSION[$REX['INSTNAME']]['UID'] > 0;
        if ($loggedIn && (!isset($REX['LOGIN']) || !is_object($REX['LOGIN']))) {
            if (!is_object($I18N)) {
                $I18N = rex_create_lang($REX['LANG']);
            }
            $REX['LOGIN'] = new rex_backend_login($REX['TABLE_PREFIX'] . 'user');
            $loggedIn = $REX['LOGIN']->checkLogin();
        }
        if ($loggedIn && $REX['LOGIN']->USER->isAdmin()) {
            rex_register_extension_point('DEVELOPER_MANAGER_START','',array(),true);
            rex_developer_manager::start();
        }
    });
}
