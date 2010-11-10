<?php

require $REX['INCLUDE_PATH'].'/layout/top.php';

echo rex_title($I18N->msg('developer_name'));

if (rex_post('func', 'string') == 'update') 
{
  $config = (array)rex_post('config','array',array());
  $config['dir'] = trim($config['dir'],'/');
  if (rex_developer_manager::saveConfig($config))
    echo rex_info($I18N->msg('developer_saved'));
  else 
    echo rex_warning($I18N->msg('developer_error'));
}

$templates = '';
if ($REX['ADDON']['developer']['config']['templates']=="1")
  $templates = ' checked="checked"';
$modules = '';
if ($REX['ADDON']['developer']['config']['modules']=="1")
  $modules = ' checked="checked"';

echo '

<div class="rex-addon-output">

<h2 class="rex-hl2">'. $I18N->msg('developer_config') .'</h2>

<div class="rex-area">
  <div class="rex-form">
	
  <form action="index.php?page=developer" method="post">

		<fieldset class="rex-form-col-1">
      <div class="rex-form-wrapper">
			  <input type="hidden" name="func" value="update" />
        
        <div class="rex-form-row">
          <p class="rex-form-checkbox rex-form-label-right">
            <input type="hidden" name="config[templates]" value="0" />
            <input class="rex-form-checkbox" type="checkbox" id="templates" name="config[templates]" value="1"'.$templates.' />
            <label for="templates">'.$I18N->msg('developer_templates').'</label>
          </p>
        </div>
        
        <div class="rex-form-row">
          <p class="rex-form-checkbox rex-form-label-right">
            <input type="hidden" name="config[modules]" value="0" />
            <input class="rex-form-checkbox" type="checkbox" id="modules" name="config[modules]" value="1"'.$modules.' />
            <label for="modules">'.$I18N->msg('developer_modules').'</label>
          </p>
        </div>
        
        <div class="rex-form-row">
          <p class="rex-form-text">
            <label for="dir">'.$I18N->msg('developer_dir').':</label>
            /redaxo/include/ <input type="text" id="dir" name="config[dir]" value="'.$REX['ADDON']['developer']['config']['dir'].'" />  
          </p>
        </div>
        
        <div class="rex-form-row">
				  <p>
            <input type="submit" class="rex-form-submit" name="FUNC_UPDATE" value="'.$I18N->msg('developer_save').'" />
          </p>
			  </div>
        
			</div>
    </fieldset>
  </form>
  </div>
</div>

</div>
  ';

require $REX['INCLUDE_PATH'].'/layout/bottom.php';