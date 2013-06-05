<?php

if (!rex::isBackend() || rex::getUser()) {
    rex_extension::register('PACKAGES_INCLUDED', function () {
        if (($user = rex_backend_login::createUser()) && $user->isAdmin()) {
            rex_developer_manager::start();
        }
    });
}
