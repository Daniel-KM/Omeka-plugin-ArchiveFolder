<?php
/**
 * Archive Folder
 *
 * Automatically build an archive from files and metadata of a local or a
 * distant folder.
 *
 * @copyright Copyright Daniel Berthereau, 2015
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 * @package ArchiveFolder
 */

/**
 * The Archive Folder plugin.
 */
class ArchiveFolderPlugin extends Omeka_Plugin_AbstractPlugin
{
    /**
     * @var array This plugin's hooks.
     */
    protected $_hooks = array(
        'initialize',
        'install',
        'upgrade',
        'uninstall',
        'uninstall_message',
        'config_form',
        'config',
        'define_acl',
        'admin_items_batch_edit_form',
        'items_batch_edit_custom',
    );

    /**
     * @var array This plugin's filters.
     */
    protected $_filters = array(
        'admin_navigation_main',
        'archive_folder_mappings',
        // It's a checkbox, so no error can be done.
        // 'items_batch_edit_error',
        // See the plugin OcrElementSet for an example (import of Xml Alto).
        // 'archive_folder_ingesters',
    );

    /**
     * @var array This plugin's options.
     */
    protected $_options = array(
        'archive_folder_static_dir' => 'documents',
        'archive_folder_processor' => '',
        'archive_folder_short_dispatcher' => null,
        'archive_folder_slow_process' => 0,
        'archive_folder_memory_limit' => null,
        // With roles, in particular if Guest User is installed.
        'archive_folder_allow_roles' => 'a:1:{i:0;s:5:"super";}',
        // Options for a new archive folder.
        'archive_folder_unreferenced_files' => 'by_file',
        'archive_folder_identifier_field' => ArchiveFolder_Importer::DEFAULT_IDFIELD,
        'archive_folder_action' => ArchiveFolder_Importer::DEFAULT_ACTION,
    );

    /**
     * Initialize the plugin.
     */
    public function hookInitialize()
    {
        add_translation_source(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'languages');

        // Get the backend settings from the security.ini file.
        // This simplifies tests too (use of local paths instead of urls).
        // TODO Probably a better location to set this.
        if (!Zend_Registry::isRegistered('archive_folder')) {
            $iniFile = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'security.ini';
            $settings = new Zend_Config_Ini($iniFile, 'archive-folder');
            Zend_Registry::set('archive_folder', $settings);
        }
    }

    /**
     * Installs the plugin.
     */
    public function hookInstall()
    {
        $db = $this->_db;

        // Create a table for folders.
        $sql = "
        CREATE TABLE IF NOT EXISTS `{$db->ArchiveFolder_Folder}` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `uri` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `identifier` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `parameters` text collate utf8_unicode_ci NOT NULL,
            `status` enum('added', 'reset', 'queued', 'progress', 'paused', 'stopped', 'killed', 'completed', 'deleted', 'error') NOT NULL,
            `messages` longtext COLLATE utf8_unicode_ci NOT NULL,
            `owner_id` int unsigned NOT NULL DEFAULT '0',
            `added` timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
            `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `uri` (`uri`),
            UNIQUE `identifier` (`identifier`),
            INDEX `modified` (`modified`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ";
        $db->query($sql);

        // Create a table to list processed records.
        $sql = "
        CREATE TABLE IF NOT EXISTS `{$db->ArchiveFolder_Record}` (
            `id` int(10) unsigned NOT NULL auto_increment,
            `folder_id` int(10) unsigned NOT NULL,
            `index` int(10) unsigned NOT NULL,
            `record_type` varchar(50) collate utf8_unicode_ci NOT NULL,
            `record_id` int(10) unsigned NOT NULL,
            `name` varchar(255) collate utf8_unicode_ci NOT NULL,
            PRIMARY KEY  (`id`),
            INDEX (`folder_id`),
            INDEX `folder_id_index` (`folder_id`, `index`),
            INDEX `record_type_record_id` (`record_type`, `record_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ";
        $db->query($sql);

        $this->_installOptions();

        // Check if there is a folder for the static repositories files, else
        // create one and protect it.
        $staticDir = FILES_DIR . DIRECTORY_SEPARATOR . get_option('archive_folder_static_dir');
        if (!file_exists($staticDir)) {
            mkdir($staticDir, 0755, true);
            copy(FILES_DIR . DIRECTORY_SEPARATOR . 'original' . DIRECTORY_SEPARATOR . 'index.html',
                $staticDir . DIRECTORY_SEPARATOR . 'index.html');
        }
    }

    /**
     * Upgrade the plugin.
     */
    public function hookUpgrade($args)
    {
        $oldVersion = $args['old_version'];
        $newVersion = $args['new_version'];
        $db = $this->_db;

        if (version_compare($oldVersion, '2.4', '<')) {
            $sql = "
                ALTER TABLE `{$db->prefix}archive_folders`
                RENAME TO `{$db->ArchiveFolder_Folder}`;
            ";
            $db->query($sql);

            // Create a table to list processed records.
            $sql = "
            CREATE TABLE IF NOT EXISTS `{$db->ArchiveFolder_Record}` (
                `id` int(10) unsigned NOT NULL auto_increment,
                `folder_id` int(10) unsigned NOT NULL,
                `index` int(10) unsigned NOT NULL,
                `record_type` varchar(50) collate utf8_unicode_ci NOT NULL,
                `record_id` int(10) unsigned NOT NULL,
                PRIMARY KEY  (`id`),
                INDEX (`folder_id`),
                INDEX `folder_id_index` (`folder_id`, `index`),
                INDEX `record_type_record_id` (`record_type`, `record_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ";
            $db->query($sql);
        }

        if (version_compare($oldVersion, '2.5.2', '<')) {
            $sql = "
                ALTER TABLE `{$db->ArchiveFolder_Record}`
                ADD `name` varchar(255) collate utf8_unicode_ci NOT NULL AFTER `record_id`
            ";
            $db->query($sql);
        }
    }

    /**
     * Uninstalls the plugin.
     */
    public function hookUninstall()
    {
        $db = $this->_db;
        $sql = "DROP TABLE IF EXISTS `$db->ArchiveFolder_Folder`";
        $db->query($sql);
        $sql = "DROP TABLE IF EXISTS `$db->ArchiveFolder_Record`";
        $db->query($sql);

        $this->_uninstallOptions();
    }

    /**
     * Add a message to the confirm form for uninstallation of the plugin.
     */
    public function hookUninstallMessage()
    {
        echo __('The folder "%s" where the xml files of the documents are saved will not be removed automatically.', get_option('archive_folder_static_dir'));
    }

    /**
     * Shows plugin configuration page.
     */
    public function hookConfigForm($args)
    {
        $view = get_view();
        echo $view->partial(
            'plugins/archive-folder-config-form.php'
        );
    }

    /**
     * Handle a submitted config form.
     *
     * @param array Options set in the config form.
     */
    public function hookConfig($args)
    {
        $post = $args['post'];
        foreach ($this->_options as $optionKey => $optionValue) {
            if (in_array($optionKey, array(
                    'archive_folder_allow_roles',
                ))) {
                $post[$optionKey] = serialize($post[$optionKey]) ?: serialize(array());
            }
            if (isset($post[$optionKey])) {
                set_option($optionKey, $post[$optionKey]);
            }
        }
    }

    /**
     * Define the plugin's access control list.
     *
     * @param array $args This array contains a reference to the zend ACL.
     */
    public function hookDefineAcl($args)
    {
        $acl = $args['acl'];
        $resource = 'ArchiveFolder_Index';

        // TODO This is currently needed for tests for an undetermined reason.
        if (!$acl->has($resource)) {
            $acl->addResource($resource);
        }
        // Hack to disable CRUD actions.
        $acl->deny(null, $resource, array('show', 'add', 'edit', 'delete'));
        $acl->deny(null, $resource);

        $roles = $acl->getRoles();

        // Check that all the roles exist, in case a plugin-added role has
        // been removed (e.g. GuestUser).
        $allowRoles = unserialize(get_option('archive_folder_allow_roles')) ?: array();
        $allowRoles = array_intersect($roles, $allowRoles);
        if ($allowRoles) {
            $acl->allow($allowRoles, $resource);
        }

        $denyRoles = array_diff($roles, $allowRoles);
        if ($denyRoles) {
            $acl->deny($denyRoles, $resource);
        }

        $resource = 'ArchiveFolder_Request';
        if (!$acl->has($resource)) {
            $acl->addResource($resource);
        }
        $acl->allow(null, $resource,
            array('index', 'file', 'folder'));
    }

    /**
     * Add a partial batch edit form.
     *
     * @return void
     */
    public function hookAdminItemsBatchEditForm($args)
    {
        $view = get_view();
        echo $view->partial(
            'forms/archive-folder-batch-edit.php'
            );
    }

    /**
     * Process the partial batch edit form.
     *
     * @return void
     */
    public function hookItemsBatchEditCustom($args)
    {
        $item = $args['item'];
        $orderByFilename = $args['custom']['archivefolder']['orderByFilename'];
        $mixImages = $args['custom']['archivefolder']['mixImages'];

        if ($orderByFilename) {
            $this->_sortFiles($item, (boolean) $mixImages);
        }
    }

    /**
     * Sort all files of an item by name and eventually sort images first.
     *
     * @param Item $item
     * @param boolean $mixImages
     * @return void
     */
    protected function _sortFiles($item, $mixImages = false)
    {
        if ($item->fileCount() < 2) {
            return;
        }

        $list = $item->Files;
        // Make a sort by name before sort by type.
        usort($list, function($fileA, $fileB) {
            return strcmp($fileA->original_filename, $fileB->original_filename);
        });
            // The sort by type doesn't remix all filenames.
            if (!$mixImages) {
                $images = array();
                $nonImages = array();
                foreach ($list as $file) {
                    // Image.
                    if (strpos($file->mime_type, 'image/') === 0) {
                        $images[] = $file;
                    }
                    // Non image.
                    else {
                        $nonImages[] = $file;
                    }
                }
                $list = array_merge($images, $nonImages);
            }

            // To avoid issues with unique index when updating (order should be
            // unique for each file of an item), all orders are reset to null before
            // true process.
            $db = $this->_db;
            $bind = array(
                $item->id,
            );
            $sql = "
                UPDATE `$db->File` files
                SET files.order = NULL
                WHERE files.item_id = ?
            ";
            $db->query($sql, $bind);

            // To avoid multiple updates, a single query is used.
            foreach ($list as &$file) {
                $file = $file->id;
            }
            // The array is made unique, because a file can be repeated.
            $list = implode(',', array_unique($list));
            $sql = "
                UPDATE `$db->File` files
                SET files.order = FIND_IN_SET(files.id, '$list')
                WHERE files.id in ($list)
            ";
            $db->query($sql);
    }

    /**
     * Add the plugin link to the admin main navigation.
     *
     * @param array Navigation array.
     * @return array Filtered navigation array.
    */
    public function filterAdminNavigationMain($nav)
    {
        $link = array(
            'label' => __('Archive Folders'),
            'uri' => url('archive-folder'),
            'resource' => 'ArchiveFolder_Index',
            'privilege' => 'index',
        );
        $nav[] = $link;

        return $nav;
    }

    /**
     * Add the mappings to convert metadata files into Omeka elements.
     *
     * @param array $mappings Available mappings.
     * @return array Filtered mappings array.
    */
    public function filterArchiveFolderMappings($mappings)
    {
        // Available mappings in the plugin at first place to keep order.
        $archiveFolderMappings = array();
        $mappings['doc'] = array(
            'class' => 'ArchiveFolder_Mapping_Document',
            'description' => __('Documents xml (simple format that manages all features of Omeka)'),
        );
        $archiveFolderMappings['text'] = array(
            'class' => 'ArchiveFolder_Mapping_Text',
            'description' => __('Text (extension: ".metadata.txt")'),
        );
        $archiveFolderMappings['json'] = array(
            'class' => 'ArchiveFolder_Mapping_Json',
            'description' => __('Json (extension: ".json")'),
        );
        $archiveFolderMappings['odt'] = array(
            'class' => 'ArchiveFolder_Mapping_Odt',
            'description' => __('Open Document Text (extension: ".odt")'),
        );
        $archiveFolderMappings['ods'] = array(
            'class' => 'ArchiveFolder_Mapping_Ods',
            'description' => __('Open Document Spreadsheet (extension: ".ods")'),
        );
        $archiveFolderMappings['omeka'] = array(
            'class' => 'ArchiveFolder_Mapping_XmlOmeka',
            'description' => __('Omeka xml'),
        );
        $archiveFolderMappings['mets'] = array(
            'class' => 'ArchiveFolder_Mapping_Mets',
            'description' => __('METS xml (with a profile compliant with Dublin Core)'),
        );
        $archiveFolderMappings['mag'] = array(
            'class' => 'ArchiveFolder_Mapping_XmlMag',
            'description' => __('MAG xml'),
        );

        return array_merge($archiveFolderMappings, $mappings);
    }
}
