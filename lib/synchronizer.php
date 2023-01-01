<?php

/**
 * Abstract base class for synchronizers
 *
 * @author gharlan
 */
abstract class rex_developer_synchronizer
{
    const ID_FILE     = '.rex_id';
    const IGNORE_FILE = '.rex_ignore';

    /** Force the current status in db  */
    const FORCE_DB = 1;

    /** Force the current status in file system */
    const FORCE_FILES = 2;

    protected $dirname;
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
        $this->dirname = $dirname;
        $this->baseDir = rex_developer_manager::getBasePath() . '/' . $dirname . '/';

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
     * The method is called, when an existing item is deleted by the file system (`FORCE_FILES` is activated)
     *
     * Use the method to delete the item in the base system
     *
     * @param rex_developer_synchronizer_item $item
     */
    protected function deleteItem(rex_developer_synchronizer_item $item)
    {
    }

    /**
     * Runs the synchronizer
     *
     * @param bool $force Flag, whether the synchronizers should run in force mode (`rex_developer_synchronizer::FORCE_DB/FILES`)
     */
    public function run($force = false)
    {
        $idLists = rex_config::get('developer', 'items', array());
        $idList = isset($idLists[$this->dirname]) ? $idLists[$this->dirname] : array();

        if (isset($idList[0])) {
            $idList = array_flip($idList);
        }

        $origIdList = $idList;

        list($existing, $new) = $this->getNewAndExistingDirs();
        $this->synchronizeReceivedItems($idList, $existing, $force);
        $this->removeItems($idList, $existing, $force);
        $this->addNewItems($idList, $existing, true);
        $this->addNewItems($idList, $new, false);

        if ($idList !== $origIdList) {
            $idLists[$this->dirname] = $idList;
            rex_config::set('developer', 'items', $idLists);
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
                        (sscanf($file, '%d' . self::ID_FILE, $id) || ($id = ((int) rex_file::get($dir . $file))))
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
        $force = $force ? (int) $force : false;

        foreach ($this->getItems() as $item) {
            $id = $item->getId();
            $name = $item->getName();

            $existingDir = null;
            if (isset($existing[$id])) {
                $existingDir = $existing[$id];
                unset($existing[$id]);
            } elseif (self::FORCE_FILES === $force) {
                $this->deleteItem($item);
                unset($idList[$id]);

                continue;
            }

            if (rex_config::get('developer', 'rename') || !$existingDir) {
                $dirBase = self::getFilename($name);

                if (rex_config::get('developer', 'dir_suffix')) {
                    $dirBase .= ' ['.$id.']';
                }

                $dir = $dirBase;
                $i = 1;
                while ((!$existingDir || !self::equalFilenames($existingDir, $dir)) && file_exists($this->baseDir . $dir)) {
                    $dir = $dirBase . ' [' . ++$i . ']';
                }
                if (!$existingDir) {
                    if (!rex_file::put($this->baseDir . $dir . '/' . $id . self::ID_FILE, '')) {
                        continue;
                    }
                } elseif (!self::equalFilenames($existingDir, $dir)) {
                    rename($this->baseDir . $existingDir, $this->baseDir . $dir);
                }
                $dir = $this->baseDir . $dir . '/';
            } else {
                $dir = $this->baseDir . $existingDir . '/';
            }

            $lastUpdated = self::FORCE_DB !== $force && isset($idList[$id]) ? $idList[$id] : 0;
            $updated = self::FORCE_FILES === $force ? 0 : max(1, $item->getUpdated());
            $dbUpdated = $updated;
            $updateFiles = array();
            $files = array();
            $prefix = '';
            if (rex_config::get('developer', 'prefix')) {
                $prefix = $id . '.' . $name . '.';
            }
            foreach ($this->files as $file) {
                $filePath = self::getFile($dir, $file, $prefix, rex_config::get('developer', 'rename'));
                $files[] = $filePath;
                
                $fileMtime = @filemtime($filePath);
                $fileExists = $fileMtime !== false;
                $fileUpdated = self::FORCE_DB !== $force && $fileExists ? $fileMtime : 0;

                if ($dbUpdated > $fileUpdated && $dbUpdated > $lastUpdated || !$fileExists) {
                    rex_file::put($filePath, $item->getFile($file));
                    touch($filePath, $updated);
                } elseif ($fileUpdated > $dbUpdated) {
                    $updated = max($updated, $fileUpdated);
                    $updateFiles[$file] = rex_file::get($filePath);
                }
            }
            if ($dbUpdated != $updated) {
                $this->editItem(new rex_developer_synchronizer_item($id, $name, $updated, $updateFiles));
            }

            $idList[$id] = $updated;
        }
    }

    private function removeItems(&$idList, &$existing, $force = false)
    {
        if (self::FORCE_FILES === $force) {
            return;
        }

        foreach ($existing as $id => $dir) {
            $dir = $this->baseDir . $dir . '/';
            if (self::FORCE_DB === $force || isset($idList[$id])) {
                unset($existing[$id]);
                unset($idList[$id]);
                if (rex_config::get('developer', 'delete')) {
                    rex_dir::delete($dir);
                } else {
                    rex_file::put($dir . self::IGNORE_FILE, '');
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
                    $addFiles[$file] = rex_file::get($filePath);
                    touch($filePath, $updated);
                } else {
                    $addFiles[$file] = '';
                }
            }
            $id = $withId ? $i : null;
            $name = strtr(basename($dir), '_', ' ');
            if ($add && $id = $this->addItem(new rex_developer_synchronizer_item($id, $name, $updated, $addFiles))) {
                rex_file::put($dir . $id . self::ID_FILE, '');
                $idList[$id] = $updated;
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
        if (!rex_config::get('developer', 'umlauts')) {
            $name = str_replace(
                array('Ä',  'Ö',  'Ü',  'ä',  'ö',  'ü',  'ß'),
                array('Ae', 'Oe', 'Ue', 'ae', 'oe', 'ue', 'ss'),
                $name
            );
            $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        }

        $name = preg_replace('@[\\\\|:<>?*"\'+]@', '', $name);
        $name = strtr($name, '[]/', '()-');

        return ltrim(rtrim($name, ' .'));
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

    public static function glob($pattern, $flags = 0)
    {
        $pattern = str_replace(['[',']',"\f[","\f]"], ["\f[","\f]",'[[]','[]]'], $pattern);
        return glob($pattern, $flags);
    }
}
