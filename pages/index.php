<?php

/** @var rex_addon $this */

echo rex_view::title($this->i18n('name'));

if (rex_post('config-submit', 'boolean')) {
    $this->setConfig(rex_post('config', [
                ['templates', 'bool'],
                ['module', 'bool'],
                ['actions', 'bool'],
                ['prefix', 'bool']
            ]));

    echo rex_view::success($this->i18n('saved'));
}

$content = '';

$content .= '
<div class="rex-form">
    <form action="' . rex_url::currentBackendPage() . '" method="post">
        <fieldset>
            <h2>' . $this->i18n('settings') . '</h2>';

$formElements = [];

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

$n = [];
$n['label'] = '<label for="rex-developer-prefix">' . $this->i18n('prefix') . '</label>';
$n['field'] = '<input type="checkbox" id="rex-developer-prefix" name="config[prefix]" value="1" ' . ($this->getConfig('prefix') ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/checkbox.php');

$formElements = [];

$n = [];
$n['field'] = '<button class="rex-button" type="submit" name="config-submit" value="1" ' . rex::getAccesskey($this->i18n('save'), 'save') . '>' . $this->i18n('save') . '</button>';
$formElements[] = $n;


$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/submit.php');

$content .= '
        </fieldset>

    </form>
</div>';

echo rex_view::contentBlock($content, '', 'block');
