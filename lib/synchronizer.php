<?php

abstract class rex_developer_synchronizer
{
  const
    ID_LIST_FILE = '.rex_id_list',
    ID_FILE = '.rex_id',
    IGNORE_FILE = '.rex_ignore';

  protected
    $baseDir,
    $files;

  public function __construct($dirname, array $files)
  {
    global $REX;
    $this->baseDir = $REX['INCLUDE_PATH'] . '/' . $REX['ADDON']['settings']['developer']['dir'] . '/' . $dirname . '/';
    $this->files = $files;
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

  public function run($force = false)
  {
    $idList = array();
    $idListFile = $this->baseDir . self::ID_LIST_FILE;
    if (file_exists($idListFile)) {
      $idList = array_flip(explode(',', rex_get_file_contents($idListFile)));
    }
    $origIdList = $idList;

    list($existing, $new) = $this->getNewAndExistingDirs();
    $this->synchronizeReceivedItems($idList, $existing, $force);
    $this->markRemovedItemsAsIgnored($idList, $existing);
    $this->addNewItems($idList, $existing, true);
    $this->addNewItems($idList, $new, false);

    if (array_diff_key($origIdList, $idList) !== array_diff_key($idList, $origIdList)) {
      self::putFile($idListFile, implode(',', array_keys($idList)));
    }
  }

  private function getNewAndExistingDirs()
  {
    $existing = array();
    $new = array();
    $dirs = glob($this->baseDir . '*', GLOB_ONLYDIR | GLOB_NOSORT | GLOB_MARK);
    if (is_array($dirs)) {
      foreach ($dirs as $dir) {
        if (!file_exists($dir . self::IGNORE_FILE)) {
          if (file_exists($dir . self::ID_FILE) && ($id = ((int) rex_get_file_contents($dir . self::ID_FILE))) > 0) {
            $existing[$id] = $dir;
          } else {
            $new[] = $dir;
          }
        }
      }
    }
    return array($existing, $new);
  }

  private function synchronizeReceivedItems(&$idList, &$existing, $force = false)
  {
    foreach ($this->getItems() as $item) {
      $id = $item->getId();
      $name = $item->getName();
      if (isset($existing[$id])) {
        $dir = $existing[$id];
        unset($existing[$id]);
      } else {
        $dir = self::getPath($this->baseDir, $name) . '/';
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
        $fileUpdated = !$force && file_exists($filePath) ? filemtime($filePath) : 0;
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
  }

  private function markRemovedItemsAsIgnored(&$idList, &$existing)
  {
    foreach ($existing as $id => $dir) {
      if (isset($idList[$id])) {
        unset($existing[$id]);
        unset($idList[$id]);
        self::putFile($dir . self::IGNORE_FILE, '');
        unlink($dir . self::ID_FILE);
      }
    }
  }

  private function addNewItems(&$idList, $dirs, $withId)
  {
    foreach ($dirs as $i => $dir) {
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
      $id = $withId ? $i : null;
      $name = strtr(basename($dir), '_', ' ');
      if ($add && $id = $this->addItem(new rex_developer_synchronizer_item($id, $name, $updated, $addFiles))) {
        self::putFile($dir . self::ID_FILE, $id);
        $idList[$id] = true;
      }
    }
  }

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
    $filename = str_replace(array('ä', 'ö', 'ü', 'ß'), array('ae', 'oe', 'ue', 'ss'), $name);
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
