<?php

class rex_developer_synchronizer
{
  var $dir;
  var $templatePath;
  var $modulePath;
  var $templatePattern;
  var $moduleInputPattern;
  var $moduleOutputPattern;

  function rex_developer_synchronizer() 
  {
    global $REX;
    $this->dir = $REX['INCLUDE_PATH'] .'/'. $REX['ADDON']['settings']['developer']['dir'];
    $this->templatePath = $this->dir .'/templates/';
    $this->modulePath = $this->dir .'/modules/';
    $this->_checkDir($this->dir);
    $this->_checkDir($this->templatePath);
    $this->_checkDir($this->modulePath);
    $this->templatePattern = $this->templatePath .'*.*.php';
    $this->moduleInputPattern = $this->modulePath .'*.input.*.php';
    $this->moduleOutputPattern = $this->modulePath .'*.output.*.php';
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

  function syncTemplates()
  {
    global $REX;
    $files = $this->_getFiles($this->templatePattern);
    $sql = $this->_sqlFactory();
    //$sql->debugsql = true;
    $sql->setQuery('SELECT id, name, content, updatedate FROM '.$REX['TABLE_PREFIX'].'template');
    $rows = $sql->getRows();
    for($i = 0; $i < $rows; ++$i)
    {
      $id = $sql->getValue('id');
      $fileUpdated = isset($files[$id]) ? filemtime($files[$id]) : 0;
      $dbUpdated = max(1, $sql->getValue('updatedate'));
      if ($fileUpdated < $dbUpdated)
      {
        $file = $this->templatePath . $this->_getFilename($id .'.'. $sql->getValue('name') .'.php');
        if (isset($files[$id]) && $files[$id] != $file)
        {
          unlink($files[$id]);
        }
        file_put_contents($file, $sql->getValue('content'));
        @chmod($file, $REX['ADDON']['fileperm']['developer']);
        $this->_updateTemplateInDB($id, filemtime($file));
      }
      elseif ($fileUpdated > $dbUpdated)
      {
        $content = addslashes(rex_get_file_contents($files[$id]));
        $this->_updateTemplateInDB($id, $fileUpdated, $content);
      }
      unset($files[$id]);
      $sql->next();
    }
    array_map('unlink', $files);
  }

  function syncModules()
  {
    global $REX;
    $inputFiles = $this->_getFiles($this->moduleInputPattern);
    $outputFiles = $this->_getFiles($this->moduleOutputPattern);
    $sql = $this->_sqlFactory();
    //$sql->debugsql = true;
    $sql->setQuery('SELECT id, name, eingabe, ausgabe, updatedate FROM '.$REX['TABLE_PREFIX'].'module');
    $rows = $sql->getRows();
    $sql2 = $this->_sqlFactory();
    for($i = 0; $i < $rows; ++$i)
    {
      $id = $sql->getValue('id');
      $inputFileUpdated = isset($inputFiles[$id]) ? filemtime($inputFiles[$id]) : 0;
      $outputFileUpdated = isset($outputFiles[$id]) ? filemtime($outputFiles[$id]) : 0;
      $dbUpdated = max(1, $sql->getValue('updatedate'));

      $newUpdatedate = 0;
      $newInput = null;
      $newOutput = null;

      if ($inputFileUpdated < $dbUpdated)
      {
        $file = $this->modulePath . $this->_getFilename($id .'.input.'. $sql->getValue('name') .'.php');
        $nameChanged = false;
        if (isset($inputFiles[$id]) && $inputFiles[$id] != $file)
        {
          unlink($inputFiles[$id]);
          $nameChanged = true;
        }
        $input = $sql->getValue('eingabe');
        if ($nameChanged || !file_exists($file) || rex_get_file_contents($file) !== $input)
        {
          file_put_contents($file, $input);
          @chmod($file, $REX['ADDON']['fileperm']['developer']);
          $newUpdatedate = filemtime($file);
        }
      }
      elseif ($inputFileUpdated > $dbUpdated)
      {
        $newUpdatedate = $inputFileUpdated;
        $newInput = addslashes(rex_get_file_contents($inputFiles[$id]));
      }

      if ($outputFileUpdated < $dbUpdated)
      {
        $file = $this->modulePath . $this->_getFilename($id .'.output.'. $sql->getValue('name') .'.php');
        $nameChanged = false;
        if (isset($outputFiles[$id]) && $outputFiles[$id] != $file)
        {
          unlink($outputFiles[$id]);
          $nameChanged = true;
        }
        $output = $sql->getValue('ausgabe');
        if ($nameChanged || !file_exists($file) || rex_get_file_contents($file) !== $output)
        {
          file_put_contents($file, $output);
          @chmod($file, $REX['ADDON']['fileperm']['developer']);
          $newUpdatedate = max($newUpdatedate, filemtime($file));
        }
      }
      elseif ($outputFileUpdated > $dbUpdated)
      {
        $newUpdatedate = max($newUpdatedate, $outputFileUpdated);
        $newOutput = addslashes(rex_get_file_contents($outputFiles[$id]));
      }

      if ($newUpdatedate)
        $this->_updateModuleInDB($id, $newUpdatedate, $newInput, $newOutput);

      unset($inputFiles[$id]);
      unset($outputFiles[$id]);
      $sql->next();
    }
    array_map('unlink', $inputFiles);
    array_map('unlink', $outputFiles);
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
      echo 1;
    }
    return new rex_sql;
  }
}