REDAXO-AddOn: developer
=======================

Version 3.3.1

Das AddOn ermöglicht es, die Templates, Module und Actions über das Dateisystem (und somit mit beliebigen Editor) zu
bearbeiten, bzw. neu anzulegen.

Mindestvoraussetzungen
----------------------

* PHP 5.3.3
* REDAXO 4.3.2

Installation
------------

1. Zip-Datei im AddOn-Ordner entpacken, der Ordner muss „developer“ heißen
2. AddOn installieren und aktivieren
3. Gegebenfalls die Einstellungen auf der Developer-Page anpassen. Standardmäßig sind alle drei Sychronisationen
   (Templates/Module/Actions) aktiviert und der Synchronisationsordner ist `redaxo/include/data/addons/developer`. Der Ordner
   sollte innerhalb des geschützen Include-Ordners liegen.

Benutzung
---------

* Innerhalb des Ordners `redaxo/include/data/addons/developer` wird bei Bedarf jeweils ein Unterordner für Templates, Module und Actions
  angelegt.
* Innerhalb der Unterordner wird für jedes einzelne Item (Template/Modul/Action) ein weiterer Unterordner angelegt.
* Diese Ordner enthalten dann die Dateien, die synchronisiert werden. Neben der `metadata.yml`, welche unter anderem den
  Namen des Items enthält, sind dies die folgenden:
    - Templates: `template.php`
    - Module: `input.php`, `output.php`
    - Actions: `preview.php`, `presave.php`, `postsave.php`
* Es wird nur synchronisiert, wenn man im Backend als Admin eingeloggt ist, dann aber auch, wenn man das Frontend aufruft.
* Es können neue Items über das Dateisystem angelegt werden. Dazu genügt es einen neuen Ordner anzulegen mit mindestens
  einer der aufgelisteten Dateien.
* Wenn die automatische Umbenennung deaktiviert ist, können die Dateien individuell umbenannt werden, sie müssen aber mit
  dem Standardnamen enden. Die `template.php` kann also zum Beispiel in `navigation.template.php` umbenannt werden.
  Developer wird die dann trotzdem finden und den Namen beibehalten. Optional kann ein Präfix bestehend aus ID und Name
  automatisch hinzugefügt werden.
* Der Item-Ordner kann beliebig umbenannt werden. Als Zuordnung dient eine Datei `X.rex-id` innerhalb des
  Ordners, die nicht gelöscht werden darf.
* Bei Umbennung über das Backend ändert Developer nichts an den Ordner- und Dateinamen, nur der Name innerhalb der
  `metadata.yml` wird aktualisiert. Über dieses Feld kann auch der Name im Backend über das Dateisystem geändert werden.
* Nach dem Löschen eines Item-Ordners (oder einzelner Dateien) werden diese neu angelegt. Die Items müssen also regulär
  über das Backend gelöscht werden.
* Nach dem Löschen eines Items über das Backend wird der Sychronisationsordner gelöscht, wenn die entsprechende Option
  nicht deaktivert ist. Ansonsten wird nur die `.rex-id` durch eine `.rex-ignore` ersetzt.

Eigene Synchronisationen
------------------------

Über PlugIns oder andere AddOns ist es möglich, eigene Sychronisationen mit dem Dateisystem hinzuzufügen. Details dazu
gibt es im [Wiki](https://github.com/gharlan/redaxo_developer/wiki/Eigene-Synchronisationen).
