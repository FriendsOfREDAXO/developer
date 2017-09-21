<?php

if (method_exists('rex', 'getConsole') && rex::getConsole()) {
    return;
}

if (
    !rex::isBackend() && $this->getConfig('sync_frontend') ||
    rex::getUser() && rex::isBackend() && $this->getConfig('sync_backend')
) {
    rex_extension::register('PACKAGES_INCLUDED', function () {
        if (($user = rex_backend_login::createUser()) && $user->isAdmin()) {
            rex_developer_manager::start();
        }
    });
}
