<?php

class rex_developer_synchronizer_default extends rex_developer_synchronizer
{
  const METADADATA_FILE = 'metadata.yml';

  protected
    $table,
    $columns,
    $metadata,
    $addedCallback,
    $editedCallback,
    $idColumn = 'id',
    $nameColumn = 'name',
    $updatedColumn = 'updatedate',
    $commonCreateUpdateColumns = true;

  public function __construct($dirname, $table, array $files, array $metadata = array())
  {
    $this->table = $table;
    $this->columns = array_flip($files);
    $this->metadata = array_merge(array('name' => 'string'), $metadata);
    $files[] = self::METADADATA_FILE;
    parent::__construct($dirname, $files);
  }

  public function setAddedCallback($callback)
  {
    $this->addedCallback = $callback;
  }

  public function setEditedCallback($callback)
  {
    $this->editedCallback = $callback;
  }

  public function setIdColumn($idColumn)
  {
    $this->idColumn = $idColumn;
  }

  public function setNameColumn($nameColumn)
  {
    $this->nameColumn = $nameColumn;
  }

  public function setUpdatedColumn($updatedColumn)
  {
    $this->updatedColumn = $updatedColumn;
  }

  public function setCommonCreateUpdateColumns($commonCreateUpdateColumns)
  {
    if ($commonCreateUpdateColumns) {
      $this->commonCreateUpdateColumns = true;
      $this->updateColumn = 'updatedate';
    } else {
      $this->commonCreateUpdateColumns = false;
    }
  }

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
      $item->setFile(self::METADADATA_FILE, rex_developer_manager::yamlEncode($metadata));
      $items[] = $item;
    }
    return $items;
  }

  protected function addItem(rex_developer_synchronizer_item $item)
  {
    global $REX;
    $sql = rex_sql::factory();
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
    if (isset($files[self::METADADATA_FILE])) {
      $metadata = rex_developer_manager::yamlDecode($files[self::METADADATA_FILE]);
      foreach ($this->metadata as $column => $type) {
        if (isset($metadata[$column])) {
          $sql->setValue($column, $sql->escape(self::toString($metadata[$column], $type)));
        }
      }
      unset($files[self::METADADATA_FILE]);
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
    if (isset($files[self::METADADATA_FILE])) {
      $metadata = rex_developer_manager::yamlDecode($files[self::METADADATA_FILE]);
      foreach ($this->metadata as $column => $type) {
        if (isset($metadata[$column])) {
          $sql->setValue($column, $sql->escape(self::toString($metadata[$column], $type)));
        }
      }
      unset($files[self::METADADATA_FILE]);
    }
    foreach ($files as $file => $content) {
      $sql->setValue($this->columns[$file], $sql->escape($content));
    }
    $sql->update();
    if ($this->editedCallback) {
      call_user_func($this->editedCallback, $item);
    }
  }

  static private function cast($value, $type)
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
      case 'string':
      default:
        return (string) $value;
    }
  }

  static private function toString($value, $type)
  {
    switch ($type) {
      case 'serialize':
        return serialize($value);
      default:
        return (string) $value;
    }
  }
}
