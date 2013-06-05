<?php

/** @var rex_addon $this */

if (!$this->hasConfig()) {
    $this->setConfig([
        'templates' => true,
        'modules' => true,
        'actions' => true,
        'prefix' => false
    ]);
}
