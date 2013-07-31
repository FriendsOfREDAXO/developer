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
                    $file = basename(self::getFile($dir, self::ID_FILE));
                    if (
                        file_exists($dir . $file) &&
                        (sscanf($file, '%d' . self::ID_FILE, $id) || ($id = ((int) rex_get_file_contents($dir . $file))))
                    ) {
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
        global $REX;

        foreach ($this->getItems() as $item) {
            $id = $item->getId();
            $name = $item->getName();
            if (isset($existing[$id])) {
                $dir = $existing[$id];
                unset($existing[$id]);
            } else {
                $dir = self::getPath($this->baseDir, $name) . '/';
                if (!self::putFile($dir . $id . self::ID_FILE, '')) {
                    continue;
                }
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
                $filePath = self::getFile($dir, $file, $prefix);
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
                unlink(self::getFile($dir, self::ID_FILE));
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
     * @return string Real File path
     */
    protected static function getFile($dir, $file, $defaultPrefix = '')
    {
        $defaultPath = $dir . self::getFilename($defaultPrefix) . $file;
        if (file_exists($defaultPath)) {
            return $defaultPath;
        }
        if (file_exists($dir . $file)) {
            return $dir . $file;
        }
        if (is_array($glob = glob($dir . '*' . $file)) && !empty($glob)) {
            return $glob[0];
        }
        return $defaultPath;
    }

    /**
     * Gets an unique path for a new file
     *
     * Special characters will be replaced by "_". If the file already exists, a suffix will be added.
     *
     * @param string $dir  Directory
     * @param string $name File name
     * @return string File path
     */
    protected static function getPath($dir, $name)
    {
        $filename = self::getFilename($name);
        $path = $dir . $filename;
        if (file_exists($path)) {
            for ($i = 1; file_exists($path); ++$i) {
                $path = $dir . $filename . '_' . $i;
            }
        }
        return $path;
    }

    /**
     * Replaces special chars
     *
     * @param string $name
     * @return string
     */
    protected static function getFilename($name)
    {
        $filename = preg_replace('@[\\\\|:<>?*"\'+]@', '', $name);
        $filename = strtr($filename, '[]/', '()-');
        return ltrim(rtrim($filename, ' .'));
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
}
