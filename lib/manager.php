<?php

abstract class rex_developer_manager
{
    private static $synchronizers = array(false => array(), true => array());

    public static function register(rex_developer_synchronizer $synchronizer, $late = false)
    {
        self::$synchronizers[(boolean) $late][] = $synchronizer;
    }

    private static function registerDefault()
    {
        global $REX;
        $page = rex_request('page', 'string');
        $subpage = rex_request('subpage', 'string');
        $function = rex_request('function', 'string', '');
        $save = rex_request('save', 'string', '');

        if ($REX['ADDON']['settings']['developer']['templates']) {
            $synchronizer = new rex_developer_synchronizer_default(
                'templates',
                $REX['TABLE_PREFIX'] . 'template',
                array('content' => 'template.php'),
                array('active' => 'boolean', 'attributes' => 'serialize')
            );
            $synchronizer->setEditedCallback(function (rex_developer_synchronizer_item $item) {
                $template = new rex_template($item->getId());
                $template->deleteCache();
            });
            self::register(
                $synchronizer,
                $page == 'template' && ((($function == 'add' || $function == 'edit') && $save == 'ja') || $function == 'delete')
            );
        }

        if ($REX['ADDON']['settings']['developer']['modules']) {
            $synchronizer = new rex_developer_synchronizer_default(
                'modules',
                $REX['TABLE_PREFIX'] . 'module',
                array('eingabe' => 'input.php', 'ausgabe' => 'output.php')
            );
            $synchronizer->setEditedCallback(function (rex_developer_synchronizer_item $item) {
                global $REX;
                $sql = rex_sql::factory();
                $sql->setQuery('
                    SELECT     DISTINCT(article.id)
                    FROM       ' . $REX['TABLE_PREFIX'] . 'article article
                    LEFT JOIN  ' . $REX['TABLE_PREFIX'] . 'article_slice slice
                    ON         article.id = slice.article_id
                    WHERE      slice.modultyp_id=' . $item->getId()
                );
                require_once $REX['INCLUDE_PATH'] . '/functions/function_rex_generate.inc.php';
                for ($i = 0, $rows = $sql->getRows(); $i < $rows; ++$i) {
                    rex_deleteCacheArticle($sql->getValue('article.id'));
                    $sql->next();
                }
            });
            self::register(
                $synchronizer,
                $page == 'module' && $subpage != 'actions' && ((($function == 'add' || $function == 'edit') && $save == '1') || $function == 'delete')
            );
        }

        if ($REX['ADDON']['settings']['developer']['actions']) {
            $synchronizer = new rex_developer_synchronizer_default(
                'actions',
                $REX['TABLE_PREFIX'] . 'action',
                array('preview' => 'preview.php', 'presave' => 'presave.php', 'postsave' => 'postsave.php'),
                array('previewmode' => 'int', 'presavemode' => 'int', 'postsavemode' => 'int')
            );
            self::register(
                $synchronizer,
                $page == 'module' && $subpage == 'actions' && ((($function == 'add' || $function == 'edit') && $save == '1') || $function == 'delete')
            );
        }
    }

    public static function start()
    {
        self::registerDefault();
        if (rex_get('page', 'string') === 'import_export' && rex_get('subpage', 'string') === 'import' && rex_get('function', 'string') === 'dbimport') {
            rex_register_extension('A1_AFTER_DB_IMPORT', function () {
                rex_developer_manager::synchronize(null, true);
            });
        } else {
            self::synchronize(false);
            rex_register_extension('OUTPUT_FILTER_CACHE', function () {
                rex_developer_manager::synchronize(true);
            });
        }
    }

    public static function synchronize($late = null, $force = false)
    {
        $run = function (rex_developer_synchronizer $synchronizer) use ($force) {
            $synchronizer->run($force);
        };
        if ($late === null) {
            foreach (self::$synchronizers as $synchronizers) {
                array_walk($synchronizers, $run);
            }
        } else {
            array_walk(self::$synchronizers[$late], $run);
        }
    }

    public static function checkDir($dir)
    {
        global $REX, $I18N;
        $path = $REX['INCLUDE_PATH'] . '/' . $dir;
        if (!@is_dir($path)) {
            @mkdir($path, $REX['DIRPERM'], true);
        }
        if (!@is_dir($path)) {
            return $I18N->msg('developer_install_make_dir', $dir);
        } elseif (!@is_writable($path . '/.')) {
            return $I18N->msg('developer_install_perm_dir', $dir);
        }
        return '';
    }

    /**
     * Returns a string containing the YAML representation of $value.
     *
     * @param array  $value  The value being encoded
     * @param number $inline The level where you switch to inline YAML
     * @return string
     */
    public static function yamlEncode(array $value, $inline = 3)
    {
        return Symfony\Component\Yaml\Yaml::dump($value, $inline, 2);
    }

    /**
     * Parses YAML into a PHP array.
     *
     * @param string $value YAML string
     * @return array
     */
    public static function yamlDecode($value)
    {
        return Symfony\Component\Yaml\Yaml::parse($value);
    }
}
