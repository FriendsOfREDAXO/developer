<?php

/** @var rex_addon $this */

if (!$this->hasConfig()) {
    $this->setConfig([
        'templates' => true,
        'modules' => true,
        'actions' => true,
        'sync_frontend' => true,
        'rename' => true,
        'prefix' => false,
        'umlauts' => true,
        'delete' => true,
    ]);
}
