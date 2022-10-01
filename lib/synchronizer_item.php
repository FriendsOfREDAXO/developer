<?php

/**
 * Class for synchronizer items
 *
 * @author gharlan
 */
class rex_developer_synchronizer_item
{
    protected $id;
    protected $name;
    protected $updated;
    protected $files;

    /**
     * Constructor
     *
     * @param int      $id      Item ID
     * @param string   $name    Item name
     * @param int      $updated Update timestamp
     * @param string[] $files   Array of the item files and their content (file=>content)
     */
    public function __construct($id, $name, $updated, array $files = array())
    {
        $this->id = $id;
        $this->name = $name;
        $this->updated = $updated;
        $this->files = $files;
    }

    /**
     * Sets the ID
     *
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Returns the ID
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets the name
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Returns the name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the update timestamp
     *
     * @param int $updated
     */
    public function setUpdated($updated)
    {
        $this->updated = $updated;
    }

    /**
     * Returns the update timestamp
     *
     * @return int
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * Sets the item files and their content
     *
     * @param string[] $files Array of item files and their content (file=>content)
     */
    public function setFiles(array $files)
    {
        $this->files = $files;
    }

    /**
     * Sets the content of an item file
     *
     * @param string $file File name
     * @param string|callable():string $content Content
     */
    public function setFile($file, $content)
    {
        $this->files[$file] = $content;
    }

    /**
     * Returns all item files and their content
     *
     * @return string[] Array of all item files and their content (file=>content)
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * Returns the content of the given item file
     *
     * @param string $file File name
     * @return string Content
     */
    public function getFile($file)
    {
        if (!isset($this->files[$file])) {
            return '';
        }
        if ((!is_string($file) || strlen($file) < 200) && is_callable($this->files[$file])) {
            $this->files[$file] = call_user_func($this->files[$file]);
        }
        return $this->files[$file];
    }
}
