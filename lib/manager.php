<?php

/**
 * Developer Manager class
 *
 * @author gharlan
 */
abstract class rex_developer_manager
{
    const START_EARLY = 0;
    const START_LATE  = 1;

    private static $synchronizers = array(
        self::START_EARLY => array(),
        self::START_LATE  => array()
    );

    /**
     * Registers a new synchronizer
     *
     * @param rex_developer_synchronizer $synchronizer The synchronizer object
     * @param int                        $start        Flag, whether the synchronizer should start at the end of the request
     */
    public static function register(rex_developer_synchronizer $synchronizer, $start = self::START_EARLY)
    {
        self::$synchronizers[$start][] = $synchronizer;
    }

    /**
     * Registers the default synchronizers for templates, modules and actions
     */
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

    /**
     * Starts the main developer process
     *
     * The method registers the default synchronizers and the extensions which will start the synchronizer objects
     */
    public static function start()
    {
        self::registerDefault();

        $page     = rex_request('page', 'string');
        $subpage  = rex_request('subpage', 'string');
        $function = rex_request('function', 'string');

        if ($page === 'import_export' && $subpage === 'import' && $function === 'dbimport') {
            rex_register_extension('A1_AFTER_DB_IMPORT', function () {
                rex_developer_manager::synchronize(null, true);
            });
        } elseif ($page === 'developer' && $function === 'update') {
            rex_register_extension('OUTPUT_FILTER_CACHE', function () {
                rex_developer_manager::synchronize(null, true);
            });
        } else {
            self::synchronize(self::START_EARLY);
            rex_register_extension('OUTPUT_FILTER_CACHE', function () {
                rex_developer_manager::synchronize(rex_developer_manager::START_LATE);
            });
        }
    }

    /**
     * Runs the synchronizer objects
     *
     * @param int|null $type  Flag, which synchronizers should start. If the value is null, all synchronizers will start
     * @param bool     $force Flag, whether the synchronizers should run in force mode or not
     * @see rex_developer_synchronizer::run
     */
    public static function synchronize($type = null, $force = false)
    {
        $run = function (rex_developer_synchronizer $synchronizer) use ($force) {
            $synchronizer->run($force);
        };
        if ($type === null) {
            foreach (self::$synchronizers as $synchronizers) {
                array_walk($synchronizers, $run);
            }
        } else {
            array_walk(self::$synchronizers[$type], $run);
        }
    }

    /**
     * Creates the given directory recursively, if it does not exist already
     *
     * @param string $dir Directory path
     * @return string Error message or empty string
     */
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
     * @param array $value  The value being encoded
     * @param int   $inline The level where you switch to inline YAML
     * @return string
     */
    public static function yamlEncode(array $value, $inline = 3)
    {
        return Symfony\Component\Yaml\Yaml::dump($value, $inline, 4);
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
