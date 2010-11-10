<?php

$dir = dirname(__FILE__);
$I18N->appendFile($dir .'/lang/');
require_once $dir .'/settings.inc.php';

$msg = '';

if (!@is_writeable($dir .'/settings.inc.php')) 
{
  $msg = $I18N->msg('developer_install_perm_settings');
}

if ($REX['ADDON']['settings']['developer']['templates'] || $REX['ADDON']['settings']['developer']['modules'])
{
  $sync_dir = $REX['INCLUDE_PATH'] .'/'. $REX['ADDON']['settings']['developer']['dir'];
  
  if (!@is_dir($sync_dir))
  {
    @mkdir($sync_dir, $REX['DIRPERM'], true);
  }
  
  if (!@is_dir($sync_dir))
  {
    $msg = $I18N->msg('developer_install_make_dir', $REX['ADDON']['settings']['developer']['dir']);
  }
  elseif (!@is_writable($sync_dir .'/.'))
  {
    $msg = $I18N->msg('developer_install_perm_dir', $REX['ADDON']['settings']['developer']['dir']);
  }
}

if ($msg != '') 
{
  $REX['ADDON']['installmsg']['developer'] = $msg;
  $REX['ADDON']['install']['developer'] = false;
} else
  $REX['ADDON']['install']['developer'] = true;