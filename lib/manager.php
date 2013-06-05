<?php

/**
 * Developer Manager class
 *
 * @author gharlan
 */
abstract class rex_developer_manager
{
    private static $synchronizers = array(false => array(), true => array());

    /**
     * Registers a new synchronizer
     *
     * @param rex_developer_synchronizer $synchronizer The synchronizer object
     * @param bool                       $late         Flag, whether the synchronizer should start at the end of the request
     */
    public static function register(rex_developer_synchronizer $synchronizer, $late = false)
    {
        self::$synchronizers[(boolean) $late][] = $synchronizer;
    }

    /**
     * Registers the default synchronizers for templates, modules and actions
     */
    private static function registerDefault()
    {
        $page = rex_be_controller::getCurrentPage();
        $function = rex_request('function', 'string', '');
        $save = rex_request('save', 'string', '');
        $addon = rex_addon::get('developer');

        if ($addon->getConfig('templates')) {
            $synchronizer = new rex_developer_synchronizer_default(
                'templates',
                rex::getTable('template'),
                array('content' => 'template.php'),
                array('active' => 'boolean', 'attributes' => 'json')
            );
            $synchronizer->setEditedCallback(function (rex_developer_synchronizer_item $item) {
                $template = new rex_template($item->getId());
                $template->deleteCache();
            });
            self::register(
                $synchronizer,
                $page == 'templates' && ((($function == 'add' || $function == 'edit') && $save == 'ja') || $function == 'delete')
            );
        }

        if ($addon->getConfig('modules')) {
            $synchronizer = new rex_developer_synchronizer_default(
                'modules',
                rex::getTable('module'),
                array('input' => 'input.php', 'output' => 'output.php')
            );
            $synchronizer->setEditedCallback(function (rex_developer_synchronizer_item $item) {
                $sql = rex_sql::factory();
                $sql->setQuery('
                    SELECT     DISTINCT(article.id)
                    FROM       ' . rex::getTable('article') . ' article
                    LEFT JOIN  ' . rex::getTable('article_slice') . ' slice
                    ON         article.id = slice.article_id
                    WHERE      slice.module_id=' . $item->getId()
                );
                for ($i = 0, $rows = $sql->getRows(); $i < $rows; ++$i) {
                    rex_article_cache::delete($sql->getValue('article.id'));
                    $sql->next();
                }
            });
            self::register(
                $synchronizer,
                $page == 'modules/modules' && ((($function == 'add' || $function == 'edit') && $save == '1') || $function == 'delete')
            );
        }

        if ($addon->getConfig('actions')) {
            $synchronizer = new rex_developer_synchronizer_default(
                'actions',
                rex::getTable('action'),
                array('preview' => 'preview.php', 'presave' => 'presave.php', 'postsave' => 'postsave.php'),
                array('previewmode' => 'int', 'presavemode' => 'int', 'postsavemode' => 'int')
            );
            self::register(
                $synchronizer,
                $page == 'modules/actions' && ((($function == 'add' || $function == 'edit') && $save == '1') || $function == 'delete')
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
        if (rex_be_controller::getCurrentPage() === 'import_export/import' && rex_get('function', 'string') === 'dbimport') {
            rex_extension::register('A1_AFTER_DB_IMPORT', function () {
                rex_developer_manager::synchronize(null, true);
            });
        } else {
            self::synchronize(false);
            rex_extension::register('RESPONSE_SHUTDOWN', function () {
                rex_developer_manager::synchronize(true);
            });
        }
    }

    /**
     * Runs the synchronizer objects
     *
     * @param bool $late  Flag, which synchronizers should start. If the value is null, all synchronizers will start
     * @param bool $force Flag, whether the synchronizers should run in force mode or not
     * @see rex_developer_synchronizer::run
     */
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
}
