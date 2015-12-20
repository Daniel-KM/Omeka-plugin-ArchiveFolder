<?php
/**
 * @copyright Daniel Berthereau, 2015
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 * @package ArchiveFolder
 */

/**
 * Base class for Archive Folder tests.
 */
class ArchiveFolder_Test_AppTestCase extends Omeka_Test_AppTestCase
{
    const PLUGIN_NAME = 'ArchiveFolder';

    protected $_allowLocalPaths = true;

    // This list of metadata files is ordered by complexity to simplify tests.
    protected $_metadataFilesByFolder = array(
        'Folder_Test' => array(
            array('json' => 'Dir_B/Renaissance_africaine.json'),
            array('text' => 'Dir_A/Directory_A.metadata.txt'),
            array('doc' => 'Dir_B/Subdir_B-A/document.xml'),
            array('doc' => 'Dir_B/Subdir_B-A/document_external.xml'),
            array('odt' => 'OpenDocument_Metadata_Dir_B.odt'),
            array('ods' => 'External_Metadata.ods'),
            array('mets' => 'Dir_B/Subdir_B-B.mets.xml'),
        ),
        'Folder_Test_Characters_Http' => array(
            array('text' => 'Non-Latin - Http.metadata.txt'),
        ),
        'Folder_Test_Characters_Local' => array(
            array('text' => 'Non-Latin - Local.metadata.txt'),
        ),
    );

    // List of black-listed files ordered by folder.
    protected $_blackListedFilesByFolder = array(
        'Folder_Test' => array(
            '.htaccess',
            'File_with_forbidden_extension.wxyz',
        ),
        'Folder_Test_Characters_Http' => array(
            '.htaccess',
        ),
        'Folder_Test_Characters_Local' => array(
        ),
    );

    public function setUp()
    {
        parent::setUp();

        // All tests are done with local paths to simplify them (no http layer).
        if ($this->_allowLocalPaths) {
            $settings = (object) array(
                'local_folders' => (object) array(
                    'allow' => '1',
                    'check_realpath' => '0',
                    'base_path' => TEST_FILES_DIR,
                ),
            );
            Zend_Registry::set('archive_folder', $settings);
        }
        // Disallow local paths (default).
        else {
            $settings = (object) array(
                'local_folders' => (object) array(
                    'allow' => '0',
                    'check_realpath' => '0',
                    'base_path' => '/var/path/to/the/folder',
                ),
            );
            Zend_Registry::set('archive_folder', $settings);
        }

        defined('TEST_FILES_WEB') or define('TEST_FILES_WEB', WEB_ROOT
            . DIRECTORY_SEPARATOR . basename(dirname(dirname(__FILE__)))
            . DIRECTORY_SEPARATOR . 'tests'
            . DIRECTORY_SEPARATOR . 'suite'
            . DIRECTORY_SEPARATOR . '_files');

        $pluginHelper = new Omeka_Test_Helper_Plugin;
        // ArchiveFolderDocument is a required plugin.
        $pluginHelper->setUp('ArchiveFolderDocument');
        $pluginHelper->setUp(self::PLUGIN_NAME);
        // OcrElementSet is an optional plugin.
        $pluginHelper->setUp('OcrElementSet');
        $pluginHelper->setUp(self::PLUGIN_NAME);

        // Allow extensions "xml" and "json".
        $whiteList = get_option(Omeka_Validate_File_Extension::WHITELIST_OPTION) . ',xml,json';
        set_option(Omeka_Validate_File_Extension::WHITELIST_OPTION, $whiteList);

        // Allow media types for "xml" and "json".
        $whiteList = get_option(Omeka_Validate_File_MimeType::WHITELIST_OPTION) . ',application/xml,text/xml,application/json';
        set_option(Omeka_Validate_File_MimeType::WHITELIST_OPTION, $whiteList);
   }

    public function tearDown()
    {
        // TODO Why is this needed for last tests?
        $this->_deleteAllRecords();

        // By construction, cached files aren't deleted when plugin is removed.
        $this->_removeCachedFiles();

        parent::tearDown();
    }

    public function assertPreConditions()
    {
        $folders = $this->db->getTable('ArchiveFolder')->findAll();
        $this->assertEquals(0, count($folders), 'There should be no archive folders.');
    }

    protected function _prepareFolderTest($uri = '', $parameters = array())
    {
        // The ods file needs an element delimiter. Other parameters uses the
        // defaults.
        if (!isset($parameters['element_delimiter'])) {
            $parameters['element_delimiter'] = '|';
        }

        $this->_folder = new ArchiveFolder();
        $this->_folder->uri = $uri ?: (TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test');
        $this->_folder->prepareParameters($parameters);
        $this->_folder->save();
    }

    protected function _deleteAllRecords()
    {
        $records = $this->db->getTable('ArchiveFolder')->findAll();
        foreach($records as $record) {
            $record->delete();
        }
        $records = $this->db->getTable('ArchiveFolder')->findAll();
        $this->assertEquals(0, count($records), 'There should be no archive folders.');
    }

    /**
     * Return the list of available classes for a filter.
     *
     * @param string $filter Name of the filter to use.
     * @return array Associative array of oai identifiers.
     */
    protected function _getFiltered($filter)
    {
        $values = apply_filters($filter, array());

        foreach ($values as $name => $value) {
            $this->assertTrue(class_exists($value['class']), __('Class "%s" does not exists.', $value['class']));
        }

        return $values;
    }

    /**
     * Remove all iles in the directory used for cache.
     */
    protected function _removeCachedFiles()
    {
        $folderCache = get_option('archive_folder_static_dir');
        $this->assertTrue(strlen($folderCache) > 0, __('The folder cache is not set.'));
        $staticDir = FILES_DIR . DIRECTORY_SEPARATOR . $folderCache;
        // Another check because deltree function is dangerous.
        $this->assertTrue(strlen($staticDir) > 4, __('The folder cache is incorrect.'));
        $this->assertEquals($staticDir, realpath($staticDir), __('The folder cache is incorrect.'));
        $this->_delTree($staticDir);

        // Rebuild the cache.
        if (!file_exists($staticDir)) {
            mkdir($staticDir, 0755, true);
            copy(FILES_DIR . DIRECTORY_SEPARATOR . 'original' . DIRECTORY_SEPARATOR . 'index.html',
                $staticDir . DIRECTORY_SEPARATOR . 'index.html');
        }
    }

    /**
     * Recursively delete a folder.
     *
     * @see https://php.net/manual/en/function.rmdir.php#110489
     * @param string $dir
     */
    protected function _delTree($dir)
    {
        $dir = realpath($dir);
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            (is_dir($path)) ? $this->_delTree($path) : unlink($path);
        }
        return rmdir($dir);
    }
}
