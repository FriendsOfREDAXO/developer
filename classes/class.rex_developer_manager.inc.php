<?php

class rex_developer_manager
{
  function saveSettings($settings = array())
  {
    global $REX;
    $mypage = 'developer';
    $REX['ADDON']['settings'][$mypage] = array_merge((array)$REX['ADDON']['settings'][$mypage], (array)$settings);
    $content = '';
    foreach ((array)$REX['ADDON']['settings'][$mypage] as $key=>$value)
      $content .= "\$REX['ADDON']['settings']['$mypage']['$key'] = \"".$value."\";\n";
    $file = $REX['INCLUDE_PATH']."/addons/$mypage/settings.inc.php";
    return rex_replace_dynamic_contents($file, $content);
  }

  function sync()
  {
    global $REX;
    $page = rex_request('page', 'string');
    $subpage = rex_request('subpage', 'string');
    $function = rex_request('function','string','');
    $save = rex_request('save','string','');

    if ($page == 'import_export')
      rex_register_extension('A1_AFTER_DB_IMPORT', array('rex_developer_manager', 'deleteFiles'));

    if (($page == 'template' && ((($function=='add' || $function=='edit') && $save=='ja') || $function=='delete'))
      || ($page == 'module' && !$subpage && ((($function=='add' || $function=='edit') && $save=='1') || $function=='delete'))
      || ($page == 'import_export' && $subpage == 'import')
      || $page == 'developer') 
    {
      rex_register_extension('OUTPUT_FILTER_CACHE', array('rex_developer_manager', '_sync'));
    }
    else
    {
      rex_developer_manager::_sync();
    }
  }

  function _sync()
  {
    global $REX;
    require_once $REX['INCLUDE_PATH'] .'/addons/developer/classes/class.rex_developer_synchronizer.inc.php';
    $sync = new rex_developer_synchronizer();
    if ($REX['ADDON']['settings']['developer']['templates'])
      $sync->syncTemplates();
    if ($REX['ADDON']['settings']['developer']['modules'])
      $sync->syncModules();
  }

  function deleteFiles()
  {
    global $REX;
    require_once $REX['INCLUDE_PATH'] .'/addons/developer/classes/class.rex_developer_synchronizer.inc.php';
    $sync = new rex_developer_synchronizer();
    $sync->deleteTemplateFiles();
    $sync->deleteModuleFiles();
  }

  function checkDir($dir)
  {
    global $REX, $I18N;
    $path = $REX['INCLUDE_PATH'] .'/'. $dir;
    if (!@is_dir($path))
    {
      @mkdir($path, $REX['ADDON']['dirperm']['developer'], true);
    }
    if (!@is_dir($path))
    {
      return $I18N->msg('developer_install_make_dir', $dir);
    }
    elseif (!@is_writable($path .'/.'))
    {
      return $I18N->msg('developer_install_perm_dir', $dir);
    }
    return '';
  }

  function deleteDir($dir)
  {
    global $REX;
    $path = $REX['INCLUDE_PATH'] .'/'. $dir;
    $files = glob($path .'/*');
    $files = array_flip($files);
    unset($files[$path .'/templates']);
    unset($files[$path .'/modules']);
    if (count($files) == 0)
    {
      rex_deleteDir($path, true);
    }
  }
}