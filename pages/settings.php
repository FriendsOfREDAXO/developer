<?php

/** @var rex_addon $this */

if (rex_post('config-submit', 'boolean')) {
    $this->setConfig(rex_post('config', [
        ['templates', 'bool'],
        ['modules', 'bool'],
        ['actions', 'bool'],
        ['yform_email', 'bool'],
        ['sync_frontend', 'bool'],
        ['sync_backend', 'bool'],
        ['rename', 'bool'],
        ['dir_suffix', 'bool'],
        ['prefix', 'bool'],
        ['umlauts', 'bool'],
        ['delete', 'bool'],
    ]));

    echo rex_view::success($this->i18n('saved'));
}

$content = '<fieldset>';

$formElements = [];

if (rex_plugin::get('structure', 'content')->isAvailable()) {
    $n = [];
    $n['label'] = '<label for="rex-developer-templates">' . $this->i18n('templates') . '</label>';
    $n['field'] = '<input type="checkbox" id="rex-developer-templates" name="config[templates]" value="1" ' . ($this->getConfig('templates') ? ' checked="checked"' : '') . ' />';
    $formElements[] = $n;

    $n = [];
    $n['label'] = '<label for="rex-developer-modules">' . $this->i18n('modules') . '</label>';
    $n['field'] = '<input type="checkbox" id="rex-developer-modules" name="config[modules]" value="1" ' . ($this->getConfig('modules') ? ' checked="checked"' : '') . ' />';
    $formElements[] = $n;

    $n = [];
    $n['label'] = '<label for="rex-developer-actions">' . $this->i18n('actions') . '</label>';
    $n['field'] = '<input type="checkbox" id="rex-developer-actions" name="config[actions]" value="1" ' . ($this->getConfig('actions') ? ' checked="checked"' : '') . ' />';
    $formElements[] = $n;
}

$yformEmail = rex_plugin::get('yform', 'email');
if ($yformEmail->isAvailable() && rex_string::versionCompare($yformEmail->getVersion(), '3.4b1', '>=')) {
    $n = [];
    $n['label'] = '<label for="rex-developer-yform-email">' . $this->i18n('yform_email') . '</label>';
    $n['field'] = '<input type="checkbox" id="rex-developer-yform-email" name="config[yform_email]" value="1" ' . ($this->getConfig('yform_email') ? ' checked="checked"' : '') . ' />';
    $formElements[] = $n;
}

$n = [];
$n['label'] = '<label for="rex-developer-sync-frontend">' . $this->i18n('sync_frontend') . '</label>';
$n['field'] = '<input type="checkbox" id="rex-developer-sync-frontend" name="config[sync_frontend]" value="1" ' . ($this->getConfig('sync_frontend') ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-developer-sync-backend">' . $this->i18n('sync_backend') . '</label>';
$n['field'] = '<input type="checkbox" id="rex-developer-sync-backend" name="config[sync_backend]" value="1" ' . ($this->getConfig('sync_backend') ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-developer-rename">' . $this->i18n('rename') . '</label>';
$n['field'] = '<input type="checkbox" id="rex-developer-rename" name="config[rename]" value="1" ' . ($this->getConfig('rename') ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-developer-dir-suffix">' . $this->i18n('dir_suffix') . '</label>';
$n['field'] = '<input type="checkbox" id="rex-developer-dir-suffix" name="config[dir_suffix]" value="1" ' . ($this->getConfig('dir_suffix') ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-developer-prefix">' . $this->i18n('prefix') . '</label>';
$n['field'] = '<input type="checkbox" id="rex-developer-prefix" name="config[prefix]" value="1" ' . ($this->getConfig('prefix') ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-developer-umlauts">' . $this->i18n('umlauts') . '</label>';
$n['field'] = '<input type="checkbox" id="rex-developer-umlauts" name="config[umlauts]" value="1" ' . ($this->getConfig('umlauts') ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-developer-delete">' . $this->i18n('delete') . '</label>';
$n['field'] = '<input type="checkbox" id="rex-developer-delete" name="config[delete]" value="1" ' . ($this->getConfig('delete') ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/checkbox.php');

$formElements = [];

$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="config-submit" value="1" ' . rex::getAccesskey($this->i18n('save'), 'save') . '>' . $this->i18n('save') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('flush', true);
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', $this->i18n('settings'));
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

echo '
    <form action="' . rex_url::currentBackendPage() . '" method="post">
        ' . $content . '
    </form>';
