<?php

if (!empty($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'image/') !== false) {
    // dont trigger sync mechnisms in image requests, so we dont acquire a session-lock
    // in media-manager requests
    return;
}

if (!rex::isBackend() && $this->getConfig('sync_frontend') || rex::getUser()) {
    rex_extension::register('PACKAGES_INCLUDED', function () {
        if (($user = rex_backend_login::createUser()) && $user->isAdmin()) {
            rex_extension::registerPoint(new rex_extension_point('DEVELOPER_MANAGER_START', '', [], true));
            rex_developer_manager::start();
        }
    });
}
