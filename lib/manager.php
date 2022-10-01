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

    private static $basePath;

    public static function setBasePath($basePath)
    {
        self::$basePath = $basePath;
    }

    public static function getBasePath()
    {
        return self::$basePath ?: rex_path::addonData('developer');
    }

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
        $page = rex_be_controller::getCurrentPage();
        // workaround for https://github.com/redaxo/redaxo/issues/2900
        $function = rex_request('function', '', null);
        $function = is_string($function) ? $function : null;
        $save = rex_request('save', 'string', '');
        $addon = rex_addon::get('developer');

        $structureContent = rex_plugin::get('structure', 'content');

        if ($structureContent->isAvailable() && $addon->getConfig('templates')) {
            $metadata = array();
            if (rex_string::versionCompare($structureContent->getVersion(), '2.9', '>=')) {
                $metadata = array('key' => 'string');
            }
            $metadata = array_merge($metadata, array('active' => 'boolean', 'attributes' => 'json'));

            $synchronizer = new rex_developer_synchronizer_default(
                'templates',
                rex::getTable('template'),
                array('content' => 'template.php'),
                $metadata
            );
            $callback = function (rex_developer_synchronizer_item $item) {
                $template = new rex_template($item->getId());
                $template->deleteCache();
            };
            $synchronizer->setEditedCallback($callback);
            $synchronizer->setDeletedCallback($callback);
            self::register(
                $synchronizer,
                $page == 'templates' && ((($function == 'add' || $function == 'edit') && $save == 'ja') || $function == 'delete') ? self::START_LATE : self::START_EARLY
            );
        }

        if ($structureContent->isAvailable() && $addon->getConfig('modules')) {
            $metadata = array();
            if (rex_string::versionCompare($structureContent->getVersion(), '2.10', '>=')) {
                $metadata = array('key' => 'string');
            }

            $synchronizer = new rex_developer_synchronizer_default(
                'modules',
                rex::getTable('module'),
                array('input' => 'input.php', 'output' => 'output.php'),
                $metadata
            );
            $callback = function (rex_developer_synchronizer_item $item) {
                $sql = rex_sql::factory();
                $sql->setQuery('
                    SELECT     DISTINCT(article.id)
                    FROM       '.rex::getTable('article').' article
                    LEFT JOIN  '.rex::getTable('article_slice').' slice
                    ON         article.id = slice.article_id
                    WHERE      slice.module_id='.$item->getId()
                );
                for ($i = 0, $rows = $sql->getRows(); $i < $rows; ++$i) {
                    rex_article_cache::delete($sql->getValue('article.id'));
                    $sql->next();
                }
            };
            $synchronizer->setEditedCallback($callback);
            $synchronizer->setDeletedCallback($callback);
            self::register(
                $synchronizer,
                $page == 'modules/modules' && ((($function == 'add' || $function == 'edit') && $save == '1') || $function == 'delete') ? self::START_LATE : self::START_EARLY
            );
        }

        if ($structureContent->isAvailable() && $addon->getConfig('actions')) {
            $synchronizer = new rex_developer_synchronizer_default(
                'actions',
                rex::getTable('action'),
                array('preview' => 'preview.php', 'presave' => 'presave.php', 'postsave' => 'postsave.php'),
                array('previewmode' => 'int', 'presavemode' => 'int', 'postsavemode' => 'int')
            );
            self::register(
                $synchronizer,
                $page == 'modules/actions' && ((($function == 'add' || $function == 'edit') && $save == '1') || $function == 'delete') ? self::START_LATE : self::START_EARLY
            );
        }

        $yformEmail = rex_plugin::get('yform', 'email');
        if ($yformEmail->isAvailable() && rex_string::versionCompare($yformEmail->getVersion(), '3.4b1', '>=') && $addon->getConfig('yform_email')) {
            $synchronizer = new rex_developer_synchronizer_default(
                'yform_email',
                rex::getTable('yform_email_template'),
                array('body' => 'body.php', 'body_html' => 'body_html.php'),
                array('mail_from' => 'string', 'mail_from_name' => 'string', 'mail_reply_to' => 'string', 'mail_reply_to_name' => 'string', 'subject' => 'string', 'attachments' => 'string')
            );
            $synchronizer->setCommonCreateUpdateColumns(false);
            self::register(
                $synchronizer,
                $page == 'yform/email/index' && ($function == 'add' || $function == 'edit' || $function == 'delete') ? self::START_LATE : self::START_EARLY
            );
        }
    }

    /**
     * Starts the main developer process
     *
     * The method registers the default synchronizers and the extensions which will start the synchronizer objects
     *
     * @param bool|int $force Flag, whether the synchronizers should run in force mode (`rex_developer_synchronizer::FORCE_DB/FILES`)
     */
    public static function start($force = false)
    {
        rex_extension::registerPoint(new rex_extension_point('DEVELOPER_MANAGER_START', '', [], true));

        self::registerDefault();

        if (method_exists('rex', 'getConsole') && rex::getConsole()) {
            self::synchronize(null, $force);
        } elseif (rex_be_controller::getCurrentPagePart(1) === 'backup') {
            rex_extension::register('BACKUP_AFTER_DB_IMPORT', function () {
                rex_developer_manager::synchronize(null, rex_developer_synchronizer::FORCE_DB);
            });
        } elseif (rex_be_controller::getCurrentPagePart(1) === 'developer' && rex_get('function', 'string') === 'update') {
            rex_extension::register('RESPONSE_SHUTDOWN', function () {
                rex_developer_manager::synchronize(null, rex_developer_synchronizer::FORCE_DB);
            });
        } else {
            self::synchronize(self::START_EARLY, $force);
            rex_extension::register('RESPONSE_SHUTDOWN', function () use ($force) {
                rex_developer_manager::synchronize(self::START_LATE, $force);
            });
        }
    }

    /**
     * Runs the synchronizer objects
     *
     * @param int|null $type  Flag, which synchronizers should start. If the value is null, all synchronizers will start
     * @param bool|int $force Flag, whether the synchronizers should run in force mode (`rex_developer_synchronizer::FORCE_DB/FILES`)
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
}
