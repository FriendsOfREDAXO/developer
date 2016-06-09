<?php

/**
 * @var rex_addon $this
 */

if (rex_string::versionCompare($this->getVersion(), '3.4.1', '<')) {
    rex_file::delete($this->getDataPath('actions/.rex_id_list'));
    rex_file::delete($this->getDataPath('modules/.rex_id_list'));
    rex_file::delete($this->getDataPath('templates/.rex_id_list'));
}

if (rex_string::versionCompare($this->getVersion(), '3.5.0', '<')) {
    $this->setConfig('sync_frontend', true);
}
