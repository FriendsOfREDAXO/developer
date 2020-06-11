<?php

/** @var rex_addon $this */

if (!$this->hasConfig()) {
    $this->setConfig([
        'templates' => true,
        'modules' => true,
        'actions' => true,
        'yform_email' => true,
        'sync_frontend' => true,
        'sync_backend' => true,
        'rename' => true,
        'dir_suffix' => true,
        'prefix' => false,
        'umlauts' => false,
        'delete' => true,
    ]);
}
