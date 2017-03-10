<?php

/** @var rex_addon $this */

if (!$this->hasConfig()) {
    $this->setConfig([
        'templates' => true,
        'modules' => true,
        'actions' => true,
        'sync_frontend' => true,
        'sync_backend' => true,
        'rename' => true,
        'prefix' => false,
        'umlauts' => true,
        'delete' => true,
    ]);
}
