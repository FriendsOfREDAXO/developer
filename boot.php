<?php

/**
 *  @var rex_addon $this
 */

if (method_exists('rex', 'getConsole') && rex::getConsole()) {
    return;
}

if (rex_addon::get('media_manager')->isAvailable() && rex_media_manager::getMediaType() && rex_media_manager::getMediaFile()) {
    return;
}

if (
    !rex::isBackend() && $this->getConfig('sync_frontend') ||
    rex::getUser() && rex::isBackend() && $this->getConfig('sync_backend')
) {
    rex_extension::register('PACKAGES_INCLUDED', function () {
        if (rex::isDebugMode() || ($user = rex_backend_login::createUser()) && $user->isAdmin()) {
            rex_developer_manager::start();
        }
    });
}

rex_extension::register('EDITOR_URL', function (rex_extension_point $ep) {
    if (!preg_match('@^rex:///(template|module|action)/(\d+)(?:/([^/]+))?@', $ep->getParam('file'), $match)) {
        return null;
    }

    $type = $match[1];
    $id = $match[2];

    if (!$this->getConfig($type.'s')) {
        return null;
    }

    if ('template' === $type) {
        $subtype = 'template';
    } elseif (!isset($match[3])) {
        return null;
    } else {
        $subtype = $match[3];
    }

    $path = rtrim(rex_developer_manager::getBasePath(), '/\\').'/'.$type.'s';

    if (!$files = rex_developer_synchronizer::glob("$path/*/$id.rex_id", GLOB_NOSORT)) {
        return null;
    }

    $path = dirname($files[0]);

    if (!$files = rex_developer_synchronizer::glob("$path/*$subtype.php", GLOB_NOSORT)) {
        return null;
    }

    return rex_editor::factory()->getUrl($files[0], $ep->getParam('line'));
}, rex_extension::LATE);
