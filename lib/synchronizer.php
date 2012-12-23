<?php

abstract class rex_developer_synchronizer
{
  const
    ID_LIST_FILE = '.rex_id_list',
    ID_FILE = '.rex_id',
    IGNORE_FILE = '.rex_ignore';

  protected
    $dirname,
    $files;

  public function __construct($dirname, array $files)
  {
    $this->dirname = $dirname;
    $this->files = $files;
  }

  public function run()
  {
    global $REX;
    $baseDir = $REX['INCLUDE_PATH'] . '/' . $REX['ADDON']['settings']['developer']['dir'] . '/' . $this->dirname . '/';
    $idList = array();
    $idListFile = $baseDir . self::ID_LIST_FILE;
    if (file_exists($idListFile)) {
      $idList = array_flip(explode(',', rex_get_file_contents($idListFile)));
    }
    $origIdList = $idList;
    $dirs = glob($baseDir . '*', GLOB_ONLYDIR | GLOB_NOSORT | GLOB_MARK);
    if (!is_array($dirs)) {
      $dirs = array();
    }
    $existing = array();
    $new = array();
    foreach ($dirs as $dir) {
      if (!file_exists($dir . self::IGNORE_FILE)) {
        if (file_exists($dir . self::ID_FILE) && ($id = ((int) rex_get_file_contents($dir . self::ID_FILE))) > 0) {
          $existing[$id] = $dir;
        } else {
          $new[] = $dir;
        }
      }
    }
    foreach ($this->getItems() as $item) {
      $id = $item->getId();
      $name = $item->getName();
      if (isset($existing[$id])) {
        $dir = $existing[$id];
        unset($existing[$id]);
      } else {
        $dir = self::getPath($baseDir, $name) . '/';
        if (!self::putFile($dir . self::ID_FILE, $id)) {
          continue;
        }
      }
      $idList[$id] = true;
      $updated = max(1, $item->getUpdated());
      $dbUpdated = $updated;
      $updateFiles = array();
      $files = array();
      foreach ($this->files as $file) {
        $filePath = self::getFile($dir, $file);
        $files[] = $filePath;
        $fileUpdated = file_exists($filePath) ? filemtime($filePath) : 0;
        if ($dbUpdated > $fileUpdated) {
          self::putFile($filePath, $item->getFile($file));
          touch($filePath, $updated);
        } elseif ($fileUpdated > $dbUpdated) {
          $updated = max($updated, $fileUpdated);
          $updateFiles[$file] = rex_get_file_contents($filePath);
        }
      }
      if ($dbUpdated != $updated) {
        foreach ($files as $file) {
          touch($file, $updated);
        }
        $this->editItem(new rex_developer_synchronizer_item($id, $name, $updated, $updateFiles));
      }
    }
    foreach ($existing as $id => $dir) {
      if (isset($idList[$id])) {
        unset($existing[$id]);
        unset($idList[$id]);
        self::putFile($dir . self::IGNORE_FILE, '');
        unlink($dir . self::ID_FILE);
      }
    }
    foreach ($new + $existing as $dir) {
      $addFiles = array();
      $add = false;
      $updated = time();
      foreach ($this->files as $file) {
        $filePath = self::getFile($dir, $file);
        if (file_exists($filePath)) {
          $add = true;
          $addFiles[$file] = rex_get_file_contents($filePath);
        } else {
          self::putFile($filePath, '');
          $addFiles[$file] = '';
        }
        touch($filePath, $updated);
      }
      if ($add && $id = $this->addItem(new rex_developer_synchronizer_item(null, basename($dir), $updated, $addFiles))) {
        self::putFile($dir . self::ID_FILE, $id);
        $idList[$id] = true;
      }
    }
    if (array_diff_key($origIdList, $idList) !== array_diff_key($idList, $origIdList)) {
      self::putFile($idListFile, implode(',', array_keys($idList)));
    }
  }

  /**
   * @return array[rex_developer_synchronizer_item]
   */
  abstract protected function getItems();

  /**
   * @param rex_developer_synchronizer_item $item
   * @return int ID of new item
   */
  abstract protected function addItem(rex_developer_synchronizer_item $item);

  /**
   * @param rex_developer_synchronizer_item $item
   */
  abstract protected function editItem(rex_developer_synchronizer_item $item);

  static protected function getFile($dir, $file)
  {
    $filePath = $dir . $file;
    if (!file_exists($filePath) && is_array($glob = glob($dir . '*' . $file)) && !empty($glob)) {
      $filePath = $glob[0];
    }
    return $filePath;
  }

  static protected function getPath($dir, $name)
  {
    $filename = strtolower($name);
    $filename = str_replace(array('ä', 'ö', 'ü', 'ß'), array('ae', 'oe', 'ue', 'ss'), $filename);
    $filename = preg_replace('/[^a-zA-Z0-9.\-+]/', '_', $filename);
    $path = $dir . $filename;
    if (file_exists($path)) {
      for ($i = 1; file_exists($path); ++$i) {
        $path = $dir . $filename . '_' . $i;
      }
    }
    return $path;
  }

  static protected function putFile($file, $content)
  {
    global $REX;
    $dir = dirname($file);
    if (!is_dir($dir)) {
      mkdir($dir, $REX['DIRPERM'], true);
      @chmod($dir, $REX['DIRPERM']);
    }
    if (is_dir($dir) && file_put_contents($file, $content)) {
      @chmod($file, $REX['FILEPERM']);
      return true;
    }
    return false;
  }
}
