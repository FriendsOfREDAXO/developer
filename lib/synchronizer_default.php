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
        $items = array();
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT * FROM `' . $this->table . '`');
        for ($i = 0, $rows = $sql->getRows(); $i < $rows; ++$i, $sql->next()) {
            $name = $sql->getValue($this->nameColumn);
            $item = new rex_developer_synchronizer_item($sql->getValue($this->idColumn), $name, $sql->getValue($this->updatedColumn));
            foreach ($this->columns as $file => $column) {
                $item->setFile($file, $sql->getValue($column));
            }
            $metadata = array();
            foreach ($this->metadata as $column => $type) {
                $metadata[$column] = self::cast($sql->getValue($column), $type);
            }
            $item->setFile(self::METADATA_FILE, function() use ($metadata) {
                return rex_developer_manager::yamlEncode($metadata);
            });
            $items[] = $item;
        }
        return $items;
    }

    /**
     * {@inheritDoc}
     */
    protected function addItem(rex_developer_synchronizer_item $item)
    {
        global $REX;
        $sql = rex_sql::factory();
        $id = $item->getId();
        if ($id) {
            $sql->setQuery('SELECT `' . $this->idColumn . '` FROM ' . $this->table . ' WHERE `' . $this->idColumn . '` = ' . $id);
            if ($sql->getRows() == 0) {
                $sql->setValue($this->idColumn, $id);
            }
        }
        $sql->setTable($this->table);
        $sql->setValue($this->nameColumn, $item->getName());
        if ($this->commonCreateUpdateColumns) {
            $user = $REX['LOGIN']->USER->getValue('login');
            $sql->setValue('updatedate', $item->getUpdated());
            $sql->setValue('updateuser', $user);
            $sql->setValue('createdate', $item->getUpdated());
            $sql->setValue('createuser', $user);
        } else {
            $sql->setValue($this->updatedColumn, $item->getUpdated());
        }
        $files = $item->getFiles();
        if (isset($files[self::METADATA_FILE])) {
            $metadata = rex_developer_manager::yamlDecode($files[self::METADATA_FILE]);
            foreach ($this->metadata as $column => $type) {
                if (isset($metadata[$column])) {
                    $sql->setValue($column, $sql->escape(self::toString($metadata[$column], $type)));
                }
            }
            unset($files[self::METADATA_FILE]);
        }
        foreach ($files as $file => $content) {
            $sql->setValue($this->columns[$file], $sql->escape($content));
        }
        if ($sql->insert()) {
            $id = $sql->getLastId();
            $item->setId($id);
            if ($this->addedCallback) {
                call_user_func($this->addedCallback, $item);
            }
            return $id;
        }
        return null;
    }

    /**
     * {@inheritDoc}
     */
    protected function editItem(rex_developer_synchronizer_item $item)
    {
        global $REX;
        $sql = rex_sql::factory();
        $sql->setTable($this->table);
        $sql->setWhere('`' . $this->idColumn . '` = ' . $item->getId());
        if ($this->commonCreateUpdateColumns) {
            $sql->setValue('updatedate', $item->getUpdated());
            $sql->setValue('updateuser', $REX['LOGIN']->USER->getValue('login'));
        } else {
            $sql->setValue($this->updatedColumn, $item->getUpdated());
        }
        $files = $item->getFiles();
        if (isset($files[self::METADATA_FILE])) {
            $metadata = rex_developer_manager::yamlDecode($files[self::METADATA_FILE]);
            foreach ($this->metadata as $column => $type) {
                if (isset($metadata[$column])) {
                    $sql->setValue($column, $sql->escape(self::toString($metadata[$column], $type)));
                }
            }
            unset($files[self::METADATA_FILE]);
        }
        foreach ($files as $file => $content) {
            $sql->setValue($this->columns[$file], $sql->escape($content));
        }
        $sql->update();
        if ($this->editedCallback) {
            call_user_func($this->editedCallback, $item);
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
     * @return string
     */
    private static function toString($value, $type)
    {
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
