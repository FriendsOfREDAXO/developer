<?php

require $REX['INCLUDE_PATH'] . '/layout/top.php';

rex_title($I18N->msg('developer_name'), $REX['ADDON']['pages']['developer']);

if (rex_post('function', 'string') == 'update') {
    $settings = (array) rex_post('settings', 'array', array());
    $settings['dir'] = trim($settings['dir'], '/');
    $msg = '';

    $msg = rex_developer_manager::checkDir($settings['dir']);
    if ($msg != '') {
        echo rex_warning($msg);
    }

    if ($msg == '') {
        $REX['ADDON']['settings']['developer'] = array_merge((array) $REX['ADDON']['settings']['developer'], (array) $settings);
        $content = "<?php\n\n";
        foreach ((array) $REX['ADDON']['settings']['developer'] as $key => $value) {
            $content .= "\$REX['ADDON']['settings']['developer']['$key'] = '" . $value . "';\n";
        }
        if (rex_put_file_contents(REX_DEVELOPER_SETTINGS_FILE, $content)) {
            echo rex_info($I18N->msg('developer_saved'));
        } else {
            echo rex_warning($I18N->msg('developer_error'));
        }
    }
}

$templates = $REX['ADDON']['settings']['developer']['templates'] ? ' checked="checked"' : '';
$modules   = $REX['ADDON']['settings']['developer']['modules'] ? ' checked="checked"' : '';
$actions   = $REX['ADDON']['settings']['developer']['actions'] ? ' checked="checked"' : '';
$rename    = $REX['ADDON']['settings']['developer']['rename'] ? ' checked="checked"' : '';
$prefix    = $REX['ADDON']['settings']['developer']['prefix'] ? ' checked="checked"' : '';
$umlauts   = $REX['ADDON']['settings']['developer']['umlauts'] ? ' checked="checked"' : '';
$delete    = $REX['ADDON']['settings']['developer']['delete'] ? ' checked="checked"' : '';

echo '

<div class="rex-addon-output">

<h2 class="rex-hl2">' . $I18N->msg('developer_settings') . '</h2>

<div class="rex-area">
    <div class="rex-form">

    <form action="index.php?page=developer" method="post">

        <fieldset class="rex-form-col-1">
            <div class="rex-form-wrapper">
                <input type="hidden" name="function" value="update" />

                <div class="rex-form-row">
                    <p class="rex-form-checkbox rex-form-label-right">
                        <input type="hidden" name="settings[templates]" value="0" />
                        <input class="rex-form-checkbox" type="checkbox" id="templates" name="settings[templates]" value="1"' . $templates . ' />
                        <label for="templates">' . $I18N->msg('developer_templates') . '</label>
                    </p>
                </div>

                <div class="rex-form-row">
                    <p class="rex-form-checkbox rex-form-label-right">
                        <input type="hidden" name="settings[modules]" value="0" />
                        <input class="rex-form-checkbox" type="checkbox" id="modules" name="settings[modules]" value="1"' . $modules . ' />
                        <label for="modules">' . $I18N->msg('developer_modules') . '</label>
                    </p>
                </div>

                <div class="rex-form-row">
                    <p class="rex-form-checkbox rex-form-label-right">
                        <input type="hidden" name="settings[actions]" value="0" />
                        <input class="rex-form-checkbox" type="checkbox" id="actions" name="settings[actions]" value="1"' . $actions . ' />
                        <label for="actions">' . $I18N->msg('developer_actions') . '</label>
                    </p>
                </div>

                <div class="rex-form-row">
                    <p class="rex-form-checkbox rex-form-label-right">
                        <input type="hidden" name="settings[rename]" value="0" />
                        <input class="rex-form-checkbox" type="checkbox" id="rename" name="settings[rename]" value="1"' . $rename . ' />
                        <label for="rename">' . $I18N->msg('developer_rename') . '</label>
                    </p>
                </div>

                <div class="rex-form-row">
                    <p class="rex-form-checkbox rex-form-label-right">
                        <input type="hidden" name="settings[prefix]" value="0" />
                        <input class="rex-form-checkbox" type="checkbox" id="prefix" name="settings[prefix]" value="1"' . $prefix . ' />
                        <label for="prefix">' . $I18N->msg('developer_prefix') . '</label>
                    </p>
                </div>

                <div class="rex-form-row">
                    <p class="rex-form-checkbox rex-form-label-right">
                        <input type="hidden" name="settings[umlauts]" value="0" />
                        <input class="rex-form-checkbox" type="checkbox" id="umlauts" name="settings[umlauts]" value="1"' . $umlauts . ' />
                        <label for="umlauts">' . $I18N->msg('developer_umlauts') . '</label>
                    </p>
                </div>

                <div class="rex-form-row">
                    <p class="rex-form-checkbox rex-form-label-right">
                        <input type="hidden" name="settings[delete]" value="0" />
                        <input class="rex-form-checkbox" type="checkbox" id="delete" name="settings[delete]" value="1"' . $delete . ' />
                        <label for="delete">' . $I18N->msg('developer_delete') . '</label>
                    </p>
                </div>

                <div class="rex-form-row">
                    <p class="rex-form-text">
                        <label for="dir">' . $I18N->msg('developer_dir') . ':</label>
                        /redaxo/include/ <input type="text" id="dir" name="settings[dir]" value="' . $REX['ADDON']['settings']['developer']['dir'] . '" />
                    </p>
                </div>

                <div class="rex-form-row">
                    <p>
                        <input type="submit" class="rex-form-submit" value="' . $I18N->msg('developer_save') . '" />
                    </p>
                </div>

            </div>
        </fieldset>
    </form>
    </div>
</div>

</div>
    ';

require $REX['INCLUDE_PATH'] . '/layout/bottom.php';
