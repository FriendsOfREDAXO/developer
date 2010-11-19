<?php

class rex_developer_synchronizer
{
  var $dir;
  var $templatePath;
  var $modulePath;
  var $actionPath;
  var $templatePattern;
  var $moduleInputPattern;
  var $moduleOutputPattern;
  var $actionPreviewPattern;
  var $actionPresavePattern;
  var $actionPostsavePattern;

  function rex_developer_synchronizer() 
  {
    global $REX;
    $this->dir = $REX['INCLUDE_PATH'] .'/'. $REX['ADDON']['settings']['developer']['dir'];
    $this->templatePath = $this->dir .'/templates/';
    $this->modulePath = $this->dir .'/modules/';
    $this->actionPath = $this->dir .'/actions/';
    $this->_checkDir($this->dir);
    $this->templatePattern = $this->templatePath .'*.*.php';
    $this->moduleInputPattern = $this->modulePath .'*.input.*.php';
    $this->moduleOutputPattern = $this->modulePath .'*.output.*.php';
    $this->actionPreviewPattern = $this->actionPath .'*.preview.*.php';
    $this->actionPresavePattern = $this->actionPath .'*.presave.*.php';
    $this->actionPostsavePattern = $this->actionPath .'*.postsave.*.php';
  }
  
  function deleteTemplateFiles()
  {
    $files = $this->_getFiles($this->templatePattern);
    array_map('unlink', $files);
  }
  
  function deleteModuleFiles()
  {
    $inputFiles = $this->_getFiles($this->moduleInputPattern);
    $outputFiles = $this->_getFiles($this->moduleOutputPattern);
    array_map('unlink', $inputFiles);
    array_map('unlink', $outputFiles);
  }
  
  function deleteActionFiles()
  {
    $previewFiles = $this->_getFiles($this->actionPreviewPattern);
    $presaveFiles = $this->_getFiles($this->actionPresavePattern);
    $postsaveFiles = $this->_getFiles($this->actionPostsavePattern);
    array_map('unlink', $previewFiles);
    array_map('unlink', $presaveFiles);
    array_map('unlink', $postsaveFiles);
  }

  function syncTemplates()
  {
    global $REX;
    $this->_checkDir($this->templatePath);
    $files = $this->_getFiles($this->templatePattern);
    $sql = $this->_sqlFactory();
    //$sql->debugsql = true;
    $sql->setQuery('SELECT id, name, content, updatedate FROM '.$REX['TABLE_PREFIX'].'template');
    $rows = $sql->getRows();
    for($i = 0; $i < $rows; ++$i)
    {
      $id = $sql->getValue('id');
      $name = $sql->getValue('name');
      $dbUpdated = max(1, $sql->getValue('updatedate'));

      $file = isset($files[$id]) ? $files[$id] : null;
      $newFile = $this->templatePath . $this->_getFilename($id .'.'. $name .'.php');
      list($newUpdatedate, $newContent) = $this->_syncFile($file, $newFile, $dbUpdated, $sql->getValue('content'));

      if ($newUpdatedate)
        $this->_updateTemplateInDB($id, $newUpdatedate, $newContent);

      unset($files[$id]);
      $sql->next();
    }
    array_map('unlink', $files);
  }

  function syncModules()
  {
    global $REX;
    $this->_checkDir($this->modulePath);
    $inputFiles = $this->_getFiles($this->moduleInputPattern);
    $outputFiles = $this->_getFiles($this->moduleOutputPattern);
    $sql = $this->_sqlFactory();
    //$sql->debugsql = true;
    $sql->setQuery('SELECT id, name, eingabe, ausgabe, updatedate FROM '.$REX['TABLE_PREFIX'].'module');
    $rows = $sql->getRows();
    for($i = 0; $i < $rows; ++$i)
    {
      $id = $sql->getValue('id');
      $name = $sql->getValue('name');
      $dbUpdated = max(1, $sql->getValue('updatedate'));

      $file = isset($inputFiles[$id]) ? $inputFiles[$id] : null;
      $newFile = $this->modulePath . $this->_getFilename($id .'.input.'. $name .'.php');
      list($newUpdatedate1, $newInput) = $this->_syncFile($file, $newFile, $dbUpdated, $sql->getValue('eingabe'));
      
      $file = isset($outputFiles[$id]) ? $outputFiles[$id] : null;
      $newFile = $this->modulePath . $this->_getFilename($id .'.output.'. $name .'.php');
      list($newUpdatedate2, $newOutput) = $this->_syncFile($file, $newFile, $dbUpdated, $sql->getValue('ausgabe'));

      $newUpdatedate = max($newUpdatedate1, $newUpdatedate2);
      if ($newUpdatedate)
        $this->_updateModuleInDB($id, $newUpdatedate, $newInput, $newOutput);

      unset($inputFiles[$id]);
      unset($outputFiles[$id]);
      $sql->next();
    }
    array_map('unlink', $inputFiles);
    array_map('unlink', $outputFiles);
  }
  
  function syncActions()
  {
    global $REX;
    $this->_checkDir($this->actionPath);
    $previewFiles = $this->_getFiles($this->actionPreviewPattern);
    $presaveFiles = $this->_getFiles($this->actionPresavePattern);
    $postsaveFiles = $this->_getFiles($this->actionPostsavePattern);
    $sql = $this->_sqlFactory();
    //$sql->debugsql = true;
    $sql->setQuery('SELECT id, name, preview, presave, postsave, updatedate FROM '.$REX['TABLE_PREFIX'].'action');
    $rows = $sql->getRows();
    for($i = 0; $i < $rows; ++$i)
    {
      $id = $sql->getValue('id');
      $name = $sql->getValue('name');
      $dbUpdated = max(1, $sql->getValue('updatedate'));

      $file = isset($previewFiles[$id]) ? $previewFiles[$id] : null;
      $newFile = $this->actionPath . $this->_getFilename($id .'.preview.'. $name .'.php');
      list($newUpdatedate1, $newPreview) = $this->_syncFile($file, $newFile, $dbUpdated, $sql->getValue('preview'));

      $file = isset($presaveFiles[$id]) ? $presaveFiles[$id] : null;
      $newFile = $this->actionPath . $this->_getFilename($id .'.presave.'. $name .'.php');
      list($newUpdatedate2, $newPresave) = $this->_syncFile($file, $newFile, $dbUpdated, $sql->getValue('presave'));

      $file = isset($postsaveFiles[$id]) ? $postsaveFiles[$id] : null;
      $newFile = $this->actionPath . $this->_getFilename($id .'.postsave.'. $name .'.php');
      list($newUpdatedate3, $newPostsave) = $this->_syncFile($file, $newFile, $dbUpdated, $sql->getValue('postsave'));

      $newUpdatedate = max($newUpdatedate1, $newUpdatedate2, $newUpdatedate3);
      if ($newUpdatedate)
        $this->_updateActionInDB($id, $newUpdatedate, $newPreview, $newPresave, $newPostsave);

      unset($previewFiles[$id]);
      unset($presaveFiles[$id]);
      unset($postsaveFiles[$id]);
      $sql->next();
    }
    array_map('unlink', $previewFiles);
    array_map('unlink', $presaveFiles);
    array_map('unlink', $postsaveFiles);
  }
  
  function _syncFile($file, $newFile, $dbUpdated, $content)
  {
    global $REX;
    $fileUpdated = file_exists($file) ? filemtime($file) : 0;
    if ($fileUpdated < $dbUpdated)
    {
      $nameChanged = false;
      if ($newFile != $file)
      {
        @unlink($file);
        $nameChanged = true;
      }
      if ($nameChanged || !file_exists($newFile) || rex_get_file_contents($newFile) !== $content)
      {
        file_put_contents($newFile, $content);
        @chmod($newFile, $REX['ADDON']['fileperm']['developer']);
        return array(filemtime($newFile), null);
      }
    }
    elseif ($fileUpdated > $dbUpdated)
    {
      return array($fileUpdated, addslashes(rex_get_file_contents($file)));
    }
  }

  function _updateTemplateInDB($id, $updatedate, $content = null)
  {
    global $REX;
    $template = new rex_template($id);
    $template->deleteCache();
    $sql = $this->_sqlFactory();
    $sql->setTable($REX['TABLE_PREFIX'].'template');
    $sql->setWhere('id = '. $id);
    if ($content !== null)
      $sql->setValue('content', $content);
    $sql->setValue('updatedate', $updatedate);
    $sql->setValue('updateuser',  $REX['LOGIN']->USER->getValue('login'));
    return $sql->update();
  }

  function _updateModuleInDB($id, $updatedate, $input = null, $output = null)
  {
    global $REX;
    $sql = $this->_sqlFactory();
    $sql->setTable($REX['TABLE_PREFIX'].'module');
    $sql->setWhere('id = '. $id);
    if ($input !== null)
      $sql->setValue('eingabe', $input);
    if ($output !== null)
      $sql->setValue('ausgabe', $output);
    $sql->setValue('updatedate', $updatedate);
    $sql->setValue('updateuser',  $REX['LOGIN']->USER->getValue('login'));
    $success = $sql->update();
    if ($input !== null || $output !== null)
    {
      $sql->setQuery('
        SELECT     DISTINCT(article.id) 
        FROM       '. $REX['TABLE_PREFIX'] .'article article
        LEFT JOIN  '. $REX['TABLE_PREFIX'] .'article_slice slice
        ON         article.id = slice.article_id
        WHERE      slice.modultyp_id='. $id
      );
      $rows = $sql->getRows();
      require_once $REX['INCLUDE_PATH'] .'/functions/function_rex_generate.inc.php';
      for ($i = 0; $i < $rows; ++$i) 
      {
    	  rex_deleteCacheArticle($sql->getValue('article.id'));
        $sql->next();
      }
    }
    return $success;
  }

  function _updateActionInDB($id, $updatedate, $preview = null, $presave = null, $postsave = null)
  {
    global $REX;
    $sql = $this->_sqlFactory();
    $sql->setTable($REX['TABLE_PREFIX'].'action');
    $sql->setWhere('id = '. $id);
    if ($preview !== null)
      $sql->setValue('preview', $preview);
    if ($presave !== null)
      $sql->setValue('presave', $presave);
    if ($postsave !== null)
      $sql->setValue('postsave', $postsave);
    $sql->setValue('updatedate', $updatedate);
    $sql->setValue('updateuser',  $REX['LOGIN']->USER->getValue('login'));
    return $sql->update();
  }

  function _getFiles($pattern)
  {
    $glob = glob($pattern);
    $files = array();
    if (is_array($glob))
    {
      foreach($glob as $file)
      {
        $filename = basename($file);
        $id = (int) substr($filename, 0, strpos($filename,'.'));
        if ($id)
          $files[$id] = $file;
      }
    }
    return $files;
  }

  function _getFilename($filename) 
  {
    global $REX, $I18N;
    $search = explode('|', $I18N->msg('special_chars'));
    $replace = explode('|', $I18N->msg('special_chars_rewrite'));
    $filename = str_replace($search, $replace, $filename);
    $filename = strtolower($filename);
    $filename = preg_replace('/[^a-zA-Z0-9.\-\+]/', '_', $filename);
    return $filename;
  }

  function _checkDir($dir)
  {
    global $REX;
    if (!is_dir($dir)) 
    {
      $ret = mkdir($dir, $REX['ADDON']['dirperm']['developer'], true);
      @chmod($dir, $REX['ADDON']['dirperm']['developer']);
      return $ret;
    }
    return true;
  }
  
  function _sqlFactory()
  {
    if (method_exists('rex_sql', 'factory'))
    {
      return rex_sql::factory();
    }
    return new rex_sql;
  }
}