<?php

/**
 * Abstract base class for synchronizers
 *
 * @author gharlan
 */
abstract class rex_developer_synchronizer
{
    const ID_LIST_FILE = '.rex_id_list';
    const ID_FILE      = '.rex_id';
    const IGNORE_FILE  = '.rex_ignore';

    protected $baseDir;
    protected $files;

    /**
     * Constructor
     *
     * @param string   $dirname Name of directory, which will be used for this synchronizer
     * @param string[] $files   Array of file names, which will be synchronized within each item directory
     */
    public function __construct($dirname, array $files)
    {
        global $REX;
        $this->baseDir = $REX['INCLUDE_PATH'] . '/' . $REX['ADDON']['settings']['developer']['dir'] . '/' . $dirname . '/';
        $this->files = $files;
    }

    /**
     * The method should return all items from the base system which should be synchronized to the file system
     *
     * @return rex_developer_synchronizer_item[]
     */
    abstract protected function getItems();

    /**
     * The method is called, when a new item is created by the file system
     *
     * Use the method to add the new item to the base system. The method should return the new ID of the item.
     *
     * @param rex_developer_synchronizer_item $item New item
     * @return int ID of new item
     */
    abstract protected function addItem(rex_developer_synchronizer_item $item);

    /**
     * The method is called, when an existing item is edited by the file system
     *
     * Use the method to edit the item in the base system
     *
     * @param rex_developer_synchronizer_item $item
     */
    abstract protected function editItem(rex_developer_synchronizer_item $item);

    /**
     * Runs the synchronizer
     *
     * @param bool $force Flag, whether all items of the base system should be handled as changed
     */
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
        $this->removeItems($idList, $existing);
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
        $dirs = self::glob($this->baseDir . '*', GLOB_ONLYDIR | GLOB_NOSORT | GLOB_MARK);
        if (is_array($dirs)) {
            foreach ($dirs as $dir) {
                if (!file_exists($dir . self::IGNORE_FILE)) {
                    $file = basename(self::getFile($dir, self::ID_FILE));
                    if (
                        file_exists($dir . $file) &&
                        (sscanf($file, '%d' . self::ID_FILE, $id) || ($id = ((int) rex_get_file_contents($dir . $file))))
                    ) {
                        if (isset($existing[$id])) {
                            trigger_error(
                                'There are two item directories with the same ID: "' . $existing[$id] . '" and "' . basename($dir) . '"',
                                E_USER_ERROR
                            );
                        }
                        $existing[$id] = basename($dir);
                    } else {
                        $new[] = basename($dir);
                    }
                }
            }
        }
        return array($existing, $new);
    }

    private function synchronizeReceivedItems(&$idList, &$existing, $force = false)
    {
        global $REX;

        foreach ($this->getItems() as $item) {
            $id = $item->getId();
            $name = $item->getName();

            $existingDir = null;
            if (isset($existing[$id])) {
                $existingDir = $existing[$id];
                unset($existing[$id]);
            }
            if ($REX['ADDON']['settings']['developer']['rename'] || !$existingDir) {
                $dirBase = self::getFilename($name);
                $dir = $dirBase;
                $i = 1;
                while (!self::equalFilenames($existingDir, $dir) && file_exists($this->baseDir . $dir)) {
                    $dir = $dirBase . ' [' . ++$i . ']';
                }
                if (!$existingDir) {
                    if (!self::putFile($this->baseDir . $dir . '/' . $id . self::ID_FILE, '')) {
                        continue;
                    }
                } elseif (!self::equalFilenames($existingDir, $dir)) {
                    rename($this->baseDir . $existingDir, $this->baseDir . $dir);
                }
                $dir = $this->baseDir . $dir . '/';
            } else {
                $dir = $this->baseDir . $existingDir . '/';
            }

            $idList[$id] = true;
            $updated = max(1, $item->getUpdated());
            $dbUpdated = $updated;
            $updateFiles = array();
            $files = array();
            $prefix = '';
            if ($REX['ADDON']['settings']['developer']['prefix']) {
                $prefix = $id . '.' . $name . '.';
            }
            foreach ($this->files as $file) {
                $filePath = self::getFile($dir, $file, $prefix, $REX['ADDON']['settings']['developer']['rename']);
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

    private function removeItems(&$idList, &$existing)
    {
        global $REX;

        foreach ($existing as $id => $dir) {
            $dir = $this->baseDir . $dir . '/';
            if (isset($idList[$id])) {
                unset($existing[$id]);
                unset($idList[$id]);
                if ($REX['ADDON']['settings']['developer']['delete']) {
                    require_once $REX['INCLUDE_PATH'] . '/functions/function_rex_generate.inc.php';
                    rex_deleteDir($dir, true);
                } else {
                    self::putFile($dir . self::IGNORE_FILE, '');
                    unlink(self::getFile($dir, self::ID_FILE));
                }
            }
        }
    }

    private function addNewItems(&$idList, $dirs, $withId)
    {
        foreach ($dirs as $i => $dir) {
            $dir = $this->baseDir . $dir . '/';
            $addFiles = array();
            $add = false;
            $updated = time();
            foreach ($this->files as $file) {
                $filePath = self::getFile($dir, $file);
                if (file_exists($filePath)) {
                    $add = true;
                    $addFiles[$file] = rex_get_file_contents($filePath);
                    touch($filePath, $updated);
                } else {
                    $addFiles[$file] = '';
                }
            }
            $id = $withId ? $i : null;
            $name = strtr(basename($dir), '_', ' ');
            if ($add && $id = $this->addItem(new rex_developer_synchronizer_item($id, $name, $updated, $addFiles))) {
                self::putFile($dir . $id . self::ID_FILE, '');
                $idList[$id] = true;
            }
        }
    }

    /**
     * Gets the real path for an item file
     *
     * Item files can be prefixed by the user, so e.g. "example.template.php" will match the item file "template.php"
     *
     * @param string $dir           Directory
     * @param string $file          File name
     * @param string $defaultPrefix Default prefix
     * @param bool   $rename
     * @return string Real File path
     */
    protected static function getFile($dir, $file, $defaultPrefix = '', $rename = false)
    {
        $defaultPath = $dir . self::getFilename($defaultPrefix . $file);
        if (file_exists($defaultPath)) {
            $path = $defaultPath;
        } elseif (file_exists($dir . $file)) {
            $path = $dir . $file;
        } elseif (is_array($glob = self::glob($dir . '*' . $file)) && !empty($glob)) {
            $path = $dir . basename($glob[0]);
        }
        if (isset($path)) {
            if ($rename && !self::equalFilenames($path, $defaultPath)) {
                rename($path, $defaultPath);
                return $defaultPath;
            }
            return $path;
        }
        return $defaultPath;
    }

    /**
     * Replaces special chars
     *
     * @param string $name
     * @return string
     */
    protected static function getFilename($name)
    {
        global $REX;
        $filename = preg_replace('@[\\\\|:<>?*"\'+]@', '', $name);
        $filename = strtr($filename, '[]/', '()-');
        if (!$REX['ADDON']['settings']['developer']['umlauts']) {
            $filename = str_replace(
                array('Ä',  'Ö',  'Ü',  'ä',  'ö',  'ü',  'ß'),
                array('Ae', 'Oe', 'Ue', 'ae', 'oe', 'ue', 'ss'),
                $filename
            );
        }
        return ltrim(rtrim($filename, ' .'));
    }

    /**
     * Checks whether the filenames are equal (independent of UTF8 NFC and NFD)
     *
     * @param string $filename1
     * @param string $filename2
     * @return bool
     */
    protected static function equalFilenames($filename1, $filename2)
    {
        $search = array("A\xcc\x88", "O\xcc\x88", "U\xcc\x88", "a\xcc\x88", "o\xcc\x88", "u\xcc\x88");
        $replace = array('Ä', 'Ö', 'Ü', 'ä', 'ö', 'ü');
        $filename1 = str_replace($search, $replace, $filename1);
        $filename2 = str_replace($search, $replace, $filename2);

        return $filename1 === $filename2;
    }

    /**
     * Puts content into the given file
     *
     * @param string $file    File path
     * @param string $content Content
     * @return bool
     */
    protected static function putFile($file, $content)
    {
        global $REX;
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, $REX['DIRPERM'], true);
            @chmod($dir, $REX['DIRPERM']);
        }
        if (is_dir($dir) && false !== file_put_contents($file, $content)) {
            @chmod($file, $REX['FILEPERM']);
            return true;
        }
        return false;
    }

    protected static function glob($pattern, $flags = 0)
    {
        $pattern = addcslashes($pattern, '[]');
        return glob($pattern, $flags);
    }
}
