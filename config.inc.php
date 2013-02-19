<?php

$mypage = 'developer';

if ($REX['REDAXO']) {
    $I18N->appendFile(__DIR__ . '/lang/');
    $REX['ADDON']['name'][$mypage] = $I18N->msg('developer_name');
}

$REX['ADDON']['perm'][$mypage] = 'admin[]';
$REX['ADDON']['author'][$mypage] = 'Gregor Harlan';
$REX['ADDON']['version'][$mypage] = '3.0-dev';

require_once __DIR__ . '/settings.inc.php';

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/lib/manager.php';
require_once __DIR__ . '/lib/synchronizer.php';
require_once __DIR__ . '/lib/synchronizer_default.php';
require_once __DIR__ . '/lib/synchronizer_item.php';

if (!$REX['REDAXO'] || is_object($REX['LOGIN'])) {
    rex_register_extension('ADDONS_INCLUDED', function ($params) {
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
            rex_developer_manager::start();
        }
    });
}
