<?php

/**
 * Synchronizer class for default synchronizers
 *
 * Default synchronize will synchronize a database table to the file system.
 * The columns can be synchronized to single files or together to a metadata YAML file.
 *
 * @author gharlan
 */
class rex_developer_synchronizer_default extends rex_developer_synchronizer
{
    const METADATA_FILE = 'metadata.yml';

    protected $table;
    protected $columns;
    protected $metadata;
    protected $addedCallback;
    protected $editedCallback;
    protected $deletedCallback;
    protected $idColumn = 'id';
    protected $nameColumn = 'name';
    protected $updatedColumn = 'updatedate';
    protected $commonCreateUpdateColumns = true;

    /**
     * Constructor
     *
     * @param string   $dirname  Name of directory, which will be used for this synchronizer
     * @param string   $table    Table name
     * @param string[] $files    An associative array column=>file which contains the columns that should be synchronized to single files
     * @param string[] $metadata An associative array column=>type which contains the columns (and their content type) that should be synchronized together to the metadata file
     */
    public function __construct($dirname, $table, array $files, array $metadata = array())
    {
        $this->table = $table;
        $this->columns = array_flip($files);
        $this->metadata = array_merge(array('name' => 'string'), $metadata);
        $files[] = self::METADATA_FILE;
        parent::__construct($dirname, $files);
    }

    /**
     * Sets a callback function which will be called if a new item is added by the file system
     *
     * @param callable $callback
     */
    public function setAddedCallback($callback)
    {
        $this->addedCallback = $callback;
    }

    /**
     * Sets a callback function which will be called if an existing item is edited by the file system
     *
     * @param callable $callback
     */
    public function setEditedCallback($callback)
    {
        $this->editedCallback = $callback;
    }

    /**
     * Sets a callback function which will be called if an existing item is deleted by the file system
     *
     * @param callable $callback
     */
    public function setDeletedCallback($callback)
    {
        $this->deletedCallback = $callback;
    }

    /**
     * Sets the name of the ID column
     *
     * @param string $idColumn Column name
     */
    public function setIdColumn($idColumn)
    {
        $this->idColumn = $idColumn;
    }

    /**
     * Sets the name of the column which contains the name of the item
     *
     * @param string $nameColumn Column name
     */
    public function setNameColumn($nameColumn)
    {
        $this->nameColumn = $nameColumn;
    }

    /**
     * Sets the name of the column which contains the updated timestamp
     *
     * @param string $updatedColumn Column name
     */
    public function setUpdatedColumn($updatedColumn)
    {
        $this->updatedColumn = $updatedColumn;
    }

    /**
     * Sets whether the table has the common create and update columns (createdate, createuser, updatedate, updateuser)
     *
     * @param bool $commonCreateUpdateColumns
     */
    public function setCommonCreateUpdateColumns($commonCreateUpdateColumns)
    {
        if ($commonCreateUpdateColumns) {
            $this->commonCreateUpdateColumns = true;
            $this->updatedColumn = 'updatedate';
        } else {
            $this->commonCreateUpdateColumns = false;
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getItems()
    {
        $defaultLang = rex::getProperty('lang');
        $lang = rex_i18n::getLocale();
        if ($defaultLang !== $lang) {
            rex_i18n::setLocale($defaultLang, false);
        }

        try {
            $items = array();
            $sql = rex_sql::factory();
            $sql->setQuery('SELECT * FROM ' . $sql->escapeIdentifier($this->table));
            for ($i = 0, $rows = $sql->getRows(); $i < $rows; ++$i, $sql->next()) {
                $name = rex_i18n::translate($sql->getValue($this->nameColumn), false);
                $item = new rex_developer_synchronizer_item($sql->getValue($this->idColumn), $name, $sql->getDateTimeValue($this->updatedColumn));
                foreach ($this->columns as $file => $column) {
                    $item->setFile($file, $sql->getValue($column));
                }
                $metadata = array();
                foreach ($this->metadata as $column => $type) {
                    $metadata[$column] = self::cast($sql->getValue($column), $type);
                }
                $item->setFile(self::METADATA_FILE, function() use ($metadata) {
                    return rex_string::yamlEncode($metadata);
                });
                $items[] = $item;
            }
        } finally {
            if ($defaultLang !== $lang) {
                rex_i18n::setLocale($lang, false);
            }
        }

        return $items;
    }

    /**
     * {@inheritDoc}
     */
    protected function addItem(rex_developer_synchronizer_item $item)
    {
        $sql = rex_sql::factory();
        $id = $item->getId();
        if ($id) {
            $sql->setQuery('SELECT ' . $sql->escapeIdentifier($this->idColumn) . ' FROM ' . $sql->escapeIdentifier($this->table) . ' WHERE ' . $sql->escapeIdentifier($this->idColumn) . ' = ' . $id);
            if ($sql->getRows() == 0) {
                $sql->setValue($this->idColumn, $id);
            }
        }
        $sql->setTable($this->table);
        $sql->setValue($this->nameColumn, $item->getName());
        if ($this->commonCreateUpdateColumns) {
            $user = rex::getUser() ? rex::getUser()->getLogin() : 'console';
            $sql->setDateTimeValue('updatedate', $item->getUpdated());
            $sql->setValue('updateuser', $user);
            $sql->setDateTimeValue('createdate', $item->getUpdated());
            $sql->setValue('createuser', $user);
        } else {
            $sql->setDateTimeValue($this->updatedColumn, $item->getUpdated());
        }
        $files = $item->getFiles();
        if (isset($files[self::METADATA_FILE])) {
            $metadata = rex_string::yamlDecode($files[self::METADATA_FILE]);
            foreach ($this->metadata as $column => $type) {
                if (array_key_exists($column, $metadata)) {
                    $sql->setValue($column, self::toString($metadata[$column], $type));
                }
            }
            unset($files[self::METADATA_FILE]);
        }
        foreach ($files as $file => $content) {
            $sql->setValue($this->columns[$file], $content);
        }
        $sql->insert();

        $id = (int) $sql->getLastId();
        $item->setId($id);
        if ($this->addedCallback) {
            call_user_func($this->addedCallback, $item);
        }
        return $id;
    }

    /**
     * {@inheritDoc}
     */
    protected function editItem(rex_developer_synchronizer_item $item)
    {
        $sql = rex_sql::factory();
        $sql->setTable($this->table);
        $sql->setWhere([$this->idColumn => $item->getId()]);
        if ($this->commonCreateUpdateColumns) {
            $sql->setDateTimeValue('updatedate', $item->getUpdated());
            $sql->setValue('updateuser', rex::getUser() ? rex::getUser()->getLogin() : 'console');
        } else {
            $sql->setDateTimeValue($this->updatedColumn, $item->getUpdated());
        }
        $files = $item->getFiles();
        if (isset($files[self::METADATA_FILE])) {
            $metadata = rex_string::yamlDecode($files[self::METADATA_FILE]);
            foreach ($this->metadata as $column => $type) {
                if (array_key_exists($column, $metadata)) {
                    $sql->setValue($column, self::toString($metadata[$column], $type));
                }
            }
            unset($files[self::METADATA_FILE]);
        }
        foreach ($files as $file => $content) {
            $sql->setValue($this->columns[$file], $content);
        }
        $sql->update();
        if ($this->editedCallback) {
            call_user_func($this->editedCallback, $item);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function deleteItem(rex_developer_synchronizer_item $item)
    {
        $sql = rex_sql::factory();
        $sql->setTable($this->table);
        $sql->setWhere([$this->idColumn => $item->getId()]);
        $sql->delete();

        if ($this->deletedCallback) {
            call_user_func($this->deletedCallback, $item);
        }
    }

    /**
     * Casts a value by the given type
     *
     * @param string $value Value
     * @param string $type  Type
     * @return mixed
     */
    private static function cast($value, $type)
    {
        if (null === $value) {
            return null;
        }

        switch ($type) {
            case 'bool':
            case 'boolean':
                return (boolean) $value;
            case 'int':
            case 'integer':
                return (integer) $value;
            case 'serialize':
                return unserialize($value);
            case 'json':
                return json_decode($value, true);
            case 'string':
            default:
                return (string) $value;
        }
    }

    /**
     * Converts a value from the given type to a string
     *
     * @param mixed  $value Value
     * @param string $type  Type
     * @return null|string
     */
    private static function toString($value, $type)
    {
        if (null === $value) {
            return null;
        }

        switch ($type) {
            case 'serialize':
                return serialize($value);
            case 'json':
                return json_encode($value);
            default:
                return (string) $value;
        }
    }
}
