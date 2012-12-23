<?php

class rex_developer_synchronizer_item
{
  protected
    $id,
    $name,
    $updated,
    $files;

  public function __construct($id, $name, $updated, array $files = array())
  {
    $this->id = $id;
    $this->name = $name;
    $this->updated = $updated;
    $this->files = $files;
  }

  public function setId($id)
  {
    $this->id = $id;
  }

  public function getId()
  {
    return $this->id;
  }

  public function setName($name)
  {
    $this->name = $name;
  }

  public function getName()
  {
    return $this->name;
  }

  public function setUpdated($updated)
  {
    $this->updated = $updated;
  }

  public function getUpdated()
  {
    return $this->updated;
  }

  public function setFiles(array $files)
  {
    $this->files = $files;
  }

  public function setFile($file, $content)
  {
    $this->files[$file] = $content;
  }

  public function getFiles()
  {
    return $this->files;
  }

  public function getFile($file)
  {
    return isset($this->files[$file]) ? $this->files[$file] : '';
  }
}
