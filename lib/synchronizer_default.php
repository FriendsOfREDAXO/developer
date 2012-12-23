<?php

class rex_developer_synchronizer_default extends rex_developer_synchronizer
{
  protected
    $table,
    $columns,
    $idColumn = 'id',
    $nameColumn = 'name',
    $updatedColumn = 'updatedate',
    $commonCreateUpdateColumns = true;

  public function __construct($dirname, $table, array $files)
  {
    parent::__construct($dirname, $files);

    $this->table = $table;
    $this->columns = array_flip($files);
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
      $item = new rex_developer_synchronizer_item($sql->getValue($this->idColumn), $sql->getValue($this->nameColumn), $sql->getValue($this->updatedColumn));
      foreach ($this->columns as $file => $column) {
        $item->setFile($file, $sql->getValue($column));
      }
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
    foreach ($item->getFiles() as $file => $content) {
      $sql->setValue($this->columns[$file], $sql->escape($content));
    }
    if ($sql->insert()) {
      return $sql->getLastId();
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
    foreach ($item->getFiles() as $file => $content) {
      $sql->setValue($this->columns[$file], $sql->escape($content));
    }
    $sql->update();
  }
}
