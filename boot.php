<?php

if (!rex::isBackend() && $this->getConfig('sync_frontend') || rex::getUser()) {
    rex_extension::register('PACKAGES_INCLUDED', function () {
        if (($user = rex_backend_login::createUser()) && $user->isAdmin()) {
            rex_extension::registerPoint(new rex_extension_point('DEVELOPER_MANAGER_START', '', [], true));
            rex_developer_manager::start();
        }
    });
}
