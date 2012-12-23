<?php

abstract class rex_developer_manager
{
  static private $synchronizers = array(false => array(), true => array());

  static public function register(rex_developer_synchronizer $synchronizer, $late = false)
  {
    self::$synchronizers[(boolean) $late][] = $synchronizer;
  }

  static private function registerDefault()
  {
    global $REX;
    $page = rex_request('page', 'string');
    $subpage = rex_request('subpage', 'string');
    $function = rex_request('function', 'string', '');
    $save = rex_request('save', 'string', '');

    if ($REX['ADDON']['settings']['developer']['templates']) {
      self::register(
        new rex_developer_synchronizer_default('templates', $REX['TABLE_PREFIX'] . 'template', array('content' => 'template.php')),
        $page == 'template' && ((($function == 'add' || $function == 'edit') && $save == 'ja') || $function == 'delete')
      );
    }
    if ($REX['ADDON']['settings']['developer']['modules']) {
      self::register(
        new rex_developer_synchronizer_default('modules', $REX['TABLE_PREFIX'] . 'module', array('eingabe' => 'input.php', 'ausgabe' => 'output.php')),
        $page == 'module' && $subpage != 'actions' && ((($function == 'add' || $function == 'edit') && $save == '1') || $function == 'delete')
      );
    }
    if ($REX['ADDON']['settings']['developer']['actions']) {
      self::register(
        new rex_developer_synchronizer_default('actions', $REX['TABLE_PREFIX'] . 'action', array('preview' => 'preview.php', 'presave' => 'presave.php', 'postsave' => 'postsave.php')),
        $page == 'module' && $subpage == 'actions' && ((($function == 'add' || $function == 'edit') && $save == '1') || $function == 'delete')
      );
    }
  }

  static public function start()
  {
    self::registerDefault();
    self::synchronize(false);
    rex_register_extension('OUTPUT_FILTER_CACHE', function () {
        rex_developer_manager::synchronize(true);
    });
  }

  static public function synchronize($late)
  {
    foreach (self::$synchronizers[$late] as $synchronizer) {
      $synchronizer->run();
    }
  }

  static public function checkDir($dir)
  {
    global $REX, $I18N;
    $path = $REX['INCLUDE_PATH'] . '/' . $dir;
    if (!@is_dir($path)) {
      @mkdir($path, $REX['DIRPERM'], true);
    }
    if (!@is_dir($path)) {
      return $I18N->msg('developer_install_make_dir', $dir);
    } elseif (!@is_writable($path . '/.')) {
      return $I18N->msg('developer_install_perm_dir', $dir);
    }
    return '';
  }
}
