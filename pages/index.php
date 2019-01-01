<?php

/** @var rex_addon $this */

echo rex_view::title($this->i18n('name'));

rex_be_controller::includeCurrentPageSubPath();
