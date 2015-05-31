<?php
/**
 * @internal This is quite an integration test because Builder is a main class.
 */
class ArchiveFolder_BuilderTest extends ArchiveFolder_Test_AppTestCase
{
    protected $_isAdminTest = true;

    public function setUp()
    {
        parent::setUp();

        // Authenticate and set the current user.
        $this->user = $this->db->getTable('User')->find(1);
        $this->_authenticateUser($this->user);
    }

    public function testConstruct()
    {
        $this->_prepareFolderTest();

        $folder = $this->_folder;
        $folders = $this->db->getTable('ArchiveFolder')->findAll();
        $this->assertEquals(1, count($folders), 'There should be one archive folders.');

        $parameters = $folder->getParameters();

        $this->assertEquals('by_file', $parameters['unreferenced_files']);
        $this->assertEquals('short_name', $parameters['oai_identifier_format']);
        $this->assertEquals(
            '[' . TEST_FILES_DIR . DIRECTORY_SEPARATOR . 'Folder_Test' . ']',
            $parameters['repository_name']);
        $this->assertEquals(
            array(
                'oai_dc', 'oai_dcq', 'mets', 'doc',
            ),
            $parameters['metadata_formats']);
        $this->assertEquals('Folder_Test', $parameters['repository_identifier']);
        $this->assertTrue($parameters['oaipmh_gateway']);
        $this->assertTrue($parameters['oaipmh_harvest']);
        $this->assertEquals('doc', $parameters['oaipmh_harvest_prefix']);

        $this->assertEquals(WEB_ROOT . '/repository/Folder_Test/', $parameters['repository_folder']);
        $this->assertEquals(WEB_ROOT . '/repository/Folder_Test.xml', $parameters['repository_url']);
        $this->assertEquals(WEB_FILES . '/' . get_option('archive_folder_static_dir') . '/Folder_Test.xml',
            $folder->getStaticRepositoryBaseUrl());

        $this->assertEquals(FILES_DIR
            . DIRECTORY_SEPARATOR . get_option('archive_folder_static_dir')
            . DIRECTORY_SEPARATOR . 'Folder_Test.xml',
            $folder->getLocalRepositoryFilepath());
        $this->assertEquals(FILES_DIR
            . DIRECTORY_SEPARATOR . get_option('archive_folder_static_dir')
            . DIRECTORY_SEPARATOR . 'Folder_Test',
            $folder->getCacheFolder());

        $this->assertEquals(ArchiveFolder::STATUS_ADDED, $folder->status);
    }

    public function testByFile()
    {
        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test'
            . DIRECTORY_SEPARATOR . 'Basic';

        $parameters = array(
            'repository_name' => 'Folder Test by File',
        );

        $expected = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Results'
            . DIRECTORY_SEPARATOR . 'StaticRepositories'
            . DIRECTORY_SEPARATOR . 'FolderTest_BasicByFile.xml';

        $this->_prepareFolderTest($uri, $parameters);
        $this->_expectedXml = $expected;
        $this->_checkFolder();
    }

    public function testUpdate()
    {
        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test'
            . DIRECTORY_SEPARATOR . 'Basic';

        $parameters = array(
            'repository_name' => 'Folder Test by File',
        );

        $expected = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Results'
            . DIRECTORY_SEPARATOR . 'StaticRepositories'
            . DIRECTORY_SEPARATOR . 'FolderTest_BasicByFile.xml';

        $this->_prepareFolderTest($uri, $parameters);
        $folder = $this->_folder;

        // Update the folder (no change).
        $folder->process(ArchiveFolder_Builder::TYPE_UPDATE);
        $this->assertEquals(ArchiveFolder::STATUS_COMPLETED, $folder->status, 'Folder update failed: ' . $folder->messages);
    }

    public function testByDirectory()
    {
        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test'
            . DIRECTORY_SEPARATOR . 'Basic';

        $parameters = array(
            'repository_name' => 'Folder Test by Directory',
            'repository_identifier' => 'BasicByDirectory',
            'unreferenced_files' => 'by_directory',
        );

        $expected = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Results'
            . DIRECTORY_SEPARATOR . 'StaticRepositories'
            . DIRECTORY_SEPARATOR . 'FolderTest_BasicByDirectory.xml';

        $this->_prepareFolderTest($uri, $parameters);
        $this->_expectedXml = $expected;
        $this->_checkFolder();
    }

    public function testSimpleFolder()
    {
        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test'
            . DIRECTORY_SEPARATOR . 'Dir_A';

        $parameters = array(
            'repository_name' => 'Folder Test Simple',
            'element_delimiter' => '|',
        );

        $expected = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Results'
            . DIRECTORY_SEPARATOR . 'StaticRepositories'
            . DIRECTORY_SEPARATOR . 'FolderTest_DirA.xml';

        $this->_prepareFolderTest($uri, $parameters);
        $this->_expectedXml = $expected;
        $this->_checkFolder();
    }

    public function testFullFolder()
    {
        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test';

        $parameters = array(
            'repository_name' => 'Folder Test Full',
            'unreferenced_files' => 'by_directory',
            'element_delimiter' => '|',
        );

        $expected = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Results'
            . DIRECTORY_SEPARATOR . 'StaticRepositories'
            . DIRECTORY_SEPARATOR . 'FolderTest_Full.xml';

        $this->_prepareFolderTest($uri, $parameters);
        $this->_expectedXml = $expected;
        $this->_checkFolder();
    }

    public function testNonLatinCharactersLocal()
    {
        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test_Characters_Local';

        $parameters = array(
            'repository_name' => 'Folder Test Characters Local',
            'element_delimiter' => '|',
        );

        $expected = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Results'
            . DIRECTORY_SEPARATOR . 'StaticRepositories'
            . DIRECTORY_SEPARATOR . 'FolderTest_CharactersLocal.xml';

        $this->_prepareFolderTest($uri, $parameters);
        $this->_expectedXml = $expected;
        $this->_checkFolder();
    }

    public function testNonLatinCharactersHttp()
    {
        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test_Characters_Http';

        $parameters = array(
            'repository_name' => 'Folder Test Characters Http',
            'element_delimiter' => '|',
        );

        $expected = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Results'
            . DIRECTORY_SEPARATOR . 'StaticRepositories'
            . DIRECTORY_SEPARATOR . 'FolderTest_CharactersHttp.xml';

        $this->_prepareFolderTest($uri, $parameters);
        $this->_expectedXml = $expected;
        $this->markTestSkipped(
            __('No test for non-latin characters via http.')
        );
        // $this->_checkFolder();
    }

    /**
     * @depends testFullFolder
     */
    public function testFullFolderUpdate()
    {
        $this->testFullFolder();

        $this->markTestSkipped(
            __('To be done: replace "document.xml" by the updated one.')
        );
    }

    protected function _checkFolder()
    {
        $folder = $this->_folder;

        $folder->process(ArchiveFolder_Builder::TYPE_CHECK);
        $this->assertEquals(ArchiveFolder::STATUS_COMPLETED, $folder->status, 'Folder check failed: ' . $folder->messages);

        $folder->process(ArchiveFolder_Builder::TYPE_UPDATE);
        $this->assertEquals(ArchiveFolder::STATUS_COMPLETED, $folder->status, 'Folder update failed: ' . $folder->messages);

        if ($folder->uri == TEST_FILES_DIR . DIRECTORY_SEPARATOR . 'Folder_Test') {
            $this->_checkCache();
        }

        $this->_checkXml();
    }

    protected function _checkCache()
    {
        $folder = $this->_folder;
        $cache = $folder->getCacheFolder();

        $originalFilepaths = $this->_iterateDirectory($folder->uri);
        $cachedFilepaths = $this->_iterateDirectory($cache);

        $metadataFiles = $this->_metadataFilesByFolder[basename($folder->uri)];
        $blackListedFiles = $this->_blackListedFilesByFolder[basename($folder->uri)];

        $this->assertEquals(
            count($originalFilepaths) - count($metadataFiles) - count($blackListedFiles),
            count($cachedFilepaths));

        foreach ($cachedFilepaths as $key => $filepath) {
            $fileExists = file_exists($filepath);
            if ($fileExists) {
                $relativePath = substr($filepath, 1 + strlen($cache));
                if (in_array($relativePath, $metadataFiles)) {
                    continue;
                }
                $key = array_search($folder->uri . DIRECTORY_SEPARATOR . $relativePath, $originalFilepaths);
                $this->assertEquals(file_get_contents($originalFilepaths[$key]),
                    file_get_contents($filepath));
            }
        }
    }

    /**
     * Assert true if two xml are equals.
     */
    protected function _checkXml()
    {
        $folder = $this->_folder;

        $xmlpath = $folder->getLocalRepositoryFilepath();
        $this->assertTrue(file_exists($xmlpath));

        // The file can be saved to simplify update of the tests.
        copy($xmlpath, sys_get_temp_dir() . '/' . basename($this->_expectedXml));

        $message = sprintf('"%s" is different from "%s".', basename($this->_expectedXml), basename($xmlpath));

        $this->assertEquals(filesize($this->_expectedXml), filesize($xmlpath), $message);

        // Because the xml is known and small, it's possible to manipulate it
        // via string functions. This is only used to clean dates.
        $expected = file_get_contents($this->_expectedXml);
        $actual = file_get_contents($xmlpath);

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
