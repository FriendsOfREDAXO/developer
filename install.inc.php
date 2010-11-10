<?php

if (!rex_is_writable(dirname(__FILE__).'/config.inc.php')) {
  $REX['ADDON']['installmsg']['developer'] = 'Die Datei config.inc.php hat keine Schreibrechte!';
  $REX['ADDON']['install']['developer'] = false;
} else
  $REX['ADDON']['install']['developer'] = true;