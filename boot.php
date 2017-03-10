<?php

if (method_exists('rex', 'getConsole') && rex::getConsole()) {
    rex::getConsole()->add(new rex_developer_command());

    return;
}

if (!rex::isBackend() && $this->getConfig('sync_frontend') || rex::getUser()) {
    rex_extension::register('PACKAGES_INCLUDED', function () {
        if (($user = rex_backend_login::createUser()) && $user->isAdmin()) {
            rex_developer_manager::start();
        }
    });
}
