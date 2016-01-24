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

    protected $_folder;

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
        'Folder_Test_Importer' => array(
            array('doc' => 'documents.xml'),
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
        // OcrElementSet is an optional plugin required for tests.
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
        $folders = $this->db->getTable('ArchiveFolder_Folder')->findAll();
        $this->assertEquals(0, count($folders), 'There should be no archive folders.');
    }

    protected function _prepareFolderTest($uri = '', $parameters = array())
    {
        $this->_folder = new ArchiveFolder_Folder();
        $this->_folder->uri = $uri ?: (TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test');
        $this->_folder->prepareParameters($parameters);
        $this->_folder->save();
    }

    protected function _deleteAllRecords()
    {
        $records = $this->db->getTable('ArchiveFolder_Folder')->findAll();
        foreach($records as $record) {
            $record->delete();
        }
        $records = $this->db->getTable('ArchiveFolder_Folder')->findAll();
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

    protected function _checkFolder()
    {
        $folder = &$this->_folder;

        $folder->process(ArchiveFolder_Builder::TYPE_CHECK);
        $this->assertEquals(ArchiveFolder_Folder::STATUS_COMPLETED, $folder->status, 'Folder check failed: ' . $folder->messages);

        $folder->process(ArchiveFolder_Builder::TYPE_UPDATE);
        $this->assertEquals(ArchiveFolder_Folder::STATUS_COMPLETED, $folder->status, 'Folder update failed: ' . $folder->messages);

        $this->_checkXml();
    }

    /**
     * Assert true if two xml are equals.
     */
    protected function _checkXml()
    {
        $folder = &$this->_folder;

        $xmlpath = $folder->getLocalRepositoryFilepath();
        $this->assertTrue(file_exists($xmlpath));

        // Because the xml is known and small, it's possible to manipulate it
        // via string functions. This allows to clean local paths and dates.
        $expected = file_get_contents($this->_expectedXml);
        $actual = file_get_contents($xmlpath);
        $actual = str_replace(TEST_FILES_DIR, '::ExampleBasePath::', $actual);
        $result = file_put_contents($xmlpath, $actual);

        // The file can be saved to simplify update of the tests.
        copy($xmlpath, sys_get_temp_dir() . '/' . basename($this->_expectedXml));

        $this->assertTrue(!empty($result));

        // Common message for all tests.
        $message = sprintf('"%s" is different from "%s".', basename($this->_expectedXml), basename($xmlpath));

        $this->assertEquals(filesize($this->_expectedXml), strlen($actual), $message);

        // Get the date from the original file.
        $needle = '<oai:datestamp>';
        $expectedDate = substr(strstr($expected, $needle), strlen($needle), 10);
        $actualDate = substr(strstr($actual, $needle), strlen($needle), 10);
        // Get the time from the previous one, specially for metsHdr, if needed.
        $needle = 'CREATEDATE="';
        $expectedTime = substr(strstr($expected, $needle . $expectedDate), strlen($needle), 20);
        $actualTime = substr(strstr($actual, $needle . $actualDate), strlen($needle), 20);

        // Use the new date and time in the original file.
        $expected = str_replace(
            array(
                '<oai:datestamp>' . $expectedDate . '</oai:datestamp>',
                'CREATEDATE="' . $expectedTime . '"',
            ),
            array(
                '<oai:datestamp>' . $actualDate . '</oai:datestamp>',
                'CREATEDATE="' . $actualTime . '"',
            ),
            $expected);

        // Remove all whitespaces to manage different implementations of xml
        // on different systems.
        $expected = preg_replace('/\s+/', '', $expected);
        $actual = preg_replace('/\s+/', '', $actual);

        // This assert allows to quick check the value.
        $this->assertEquals(strlen($expected), strlen($actual), $message);

        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * List recursively a directory to get all files.
     *
     * @param string $path Path of the directory to check.
     *
     * @return associative array of dirpath / dirname and filepath / filename or
     * false if error.
     */
    private function _iterateDirectory($path)
    {
        $filenames = array();

        $path = realpath($path);
        $directoryIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach ($directoryIterator as $name => $pathObject) {
            if (!$pathObject->isDir()) {
                $filenames[] = $name;
            }
        }

        ksort($filenames);

        return $filenames;
    }
}
