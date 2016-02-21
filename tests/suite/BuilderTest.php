<?php
/**
 * @internal This is quite an integration test because Builder is a main class.
 */
class ArchiveFolder_BuilderTest extends ArchiveFolder_Test_AppTestCase
{
    protected $_isAdminTest = true;

    protected $expectedBaseDir = '';

    public function setUp()
    {
        parent::setUp();

        // Authenticate and set the current user.
        $this->user = $this->db->getTable('User')->find(1);
        $this->_authenticateUser($this->user);

        $this->_expectedBaseDir = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Results'
            . DIRECTORY_SEPARATOR . 'Documents';
    }

    public function testConstruct()
    {
        $this->_prepareFolderTest();

        $folder = &$this->_folder;
        $folders = $this->db->getTable('ArchiveFolder_Folder')->findAll();
        $this->assertEquals(1, count($folders), 'There should be one archive folders.');

        $parameters = $folder->getParameters();

        $this->assertEquals('by_file', $parameters['unreferenced_files']);
        $this->assertEquals(TEST_FILES_DIR . DIRECTORY_SEPARATOR . 'Folder_Test', $folder->uri);
        $this->assertEquals(ArchiveFolder_Folder::STATUS_ADDED, $folder->status);
    }

    public function testByFile()
    {
        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test'
            . DIRECTORY_SEPARATOR . 'Basic';

        $parameters = array();

        $this->_expectedXml = $this->_expectedBaseDir
            . DIRECTORY_SEPARATOR . 'FolderTest_BasicByFile.xml';

        $this->_prepareFolderTest($uri, $parameters);
        $this->_checkFolder();
    }

    public function testUpdate()
    {
        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test'
            . DIRECTORY_SEPARATOR . 'Basic';

        $parameters = array();

        $this->_expectedXml = $this->_expectedBaseDir
            . DIRECTORY_SEPARATOR . 'FolderTest_BasicByFile.xml';

        $this->_prepareFolderTest($uri, $parameters);
        $folder = &$this->_folder;

        // Update the folder (no change).
        $folder->process(ArchiveFolder_Builder::TYPE_UPDATE);
        $this->assertEquals(ArchiveFolder_Folder::STATUS_COMPLETED, $folder->status, 'Folder update failed: ' . $folder->messages);
    }

    public function testByDirectory()
    {
        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test'
            . DIRECTORY_SEPARATOR . 'Basic';

        $parameters = array(
            'repository_identifier' => 'BasicByDirectory',
            'unreferenced_files' => 'by_directory',
        );

        $this->_expectedXml = $this->_expectedBaseDir
            . DIRECTORY_SEPARATOR . 'FolderTest_BasicByDirectory.xml';

        $this->_prepareFolderTest($uri, $parameters);
        $this->_checkFolder();
    }

    public function testSimpleFolder()
    {
        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test'
            . DIRECTORY_SEPARATOR . 'Dir_A';

        $parameters = array();

        $this->_expectedXml = $this->_expectedBaseDir
            . DIRECTORY_SEPARATOR . 'FolderTest_DirA.xml';

        $this->_prepareFolderTest($uri, $parameters);
        $this->_checkFolder();
    }

    public function testFullFolder()
    {
        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test';

        $parameters = array(
            'unreferenced_files' => 'by_directory',
        );

        $this->_expectedXml = $this->_expectedBaseDir
            . DIRECTORY_SEPARATOR . 'FolderTest_Full.xml';

        $this->_prepareFolderTest($uri, $parameters);
        $this->_checkFolder();
    }

    public function testMetsAlto()
    {
        if (!plugin_is_active('OcrElementSet')) {
            $this->markTestSkipped(
                __('This test requires OcrElementSet.')
            );
        }

        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test_Mets_Alto';

        $parameters = array();

        $this->_expectedXml = $this->_expectedBaseDir
            . DIRECTORY_SEPARATOR . 'FolderTest_Mets_Alto.xml';

        $this->_prepareFolderTest($uri, $parameters);
        $this->_checkFolder();
    }

    public function testNonLatinCharactersLocal()
    {
        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test_Characters_Local';

        $parameters = array();

        $this->_expectedXml = $this->_expectedBaseDir
            . DIRECTORY_SEPARATOR . 'FolderTest_CharactersLocal.xml';

        $this->_prepareFolderTest($uri, $parameters);
        $this->_checkFolder();
    }

    public function testNonLatinCharactersHttp()
    {
        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test_Characters_Http';

        $parameters = array();

        $this->_expectedXml = $this->_expectedBaseDir
            . DIRECTORY_SEPARATOR . 'FolderTest_CharactersHttp.xml';

        $this->_prepareFolderTest($uri, $parameters);
        $this->markTestSkipped(
            __('No test for non-latin characters via http.')
        );
        $this->_checkFolder();
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
}
