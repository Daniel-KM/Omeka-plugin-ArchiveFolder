<?php
class ArchiveFolder_SecurityTest extends ArchiveFolder_Test_AppTestCase
{
    protected $_isAdminTest = true;

    protected $_allowLocalPaths = false;

    public function testDisallowLocalPath()
    {
        $settings = Zend_Registry::get('archive_folder');
        $this->assertEquals('0', $settings->local_folders->allow);

        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test'
            . DIRECTORY_SEPARATOR . 'Basic';

        $parameters = array(
            'repository_name' => 'Check Security',
        );

        $this->_prepareFolderTest($uri, $parameters);

        // Process folder to check error.
        $folder = $this->_folder;

        $folder->process(ArchiveFolder_Builder::TYPE_CHECK);
        $this->assertEquals(ArchiveFolder::STATUS_ERROR, $folder->status);
        $this->assertStringEndsWith(__('Local paths are not allowed by the administrator.'), $folder->messages);
    }

    public function testOutsidePath()
    {
        $settings = (object) array(
            'local_folders' => (object) array(
                'allow' => '1',
                'base_path' => TEST_FILES_DIR . DIRECTORY_SEPARATOR . 'Folder_Test',
            ),
        );
        Zend_Registry::set('archive_folder', $settings);

        $settings = Zend_Registry::get('archive_folder');
        $this->assertEquals('1', $settings->local_folders->allow);

        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test_Security';

        $parameters = array(
            'repository_name' => 'Check Security',
        );

        $this->_prepareFolderTest($uri, $parameters);

        // Process folder to check error.
        $folder = $this->_folder;

        $folder->process(ArchiveFolder_Builder::TYPE_CHECK);
        $this->assertEquals(ArchiveFolder::STATUS_ERROR, $folder->status);
        $this->assertStringEndsWith(__('The uri "%s" is not allowed.', $uri), $folder->messages);
    }
}
