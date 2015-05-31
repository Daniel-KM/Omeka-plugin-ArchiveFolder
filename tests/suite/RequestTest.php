<?php
class ArchiveFolder_RequestTest extends ArchiveFolder_Test_AppTestCase
{
    protected $_isAdminTest = false;

    public function testWrongFile()
    {
        $this->_prepareFolderTest();
        $folder = $this->_folder;

        $url = '/repository/wrong_folder/' . 'wrong_image.png';
        $folder->process(ArchiveFolder_Builder::TYPE_UPDATE);
        $this->assertEquals(ArchiveFolder::STATUS_COMPLETED, $folder->status, 'Folder update failed: ' . $folder->messages);
        $this->dispatch($url);
        $this->assertResponseCode(404);

        $url = '/repository/Folder_Test/' . 'wrong_image.png';
        $folder->process(ArchiveFolder_Builder::TYPE_UPDATE);
        $this->assertEquals(ArchiveFolder::STATUS_COMPLETED, $folder->status, 'Folder update failed: ' . $folder->messages);
        $this->dispatch($url);
        $this->assertResponseCode(500);

        $url = '/repository/Folder_Test/' . 'image_Root_#1.png';
        $this->dispatch($url);
        $this->assertResponseCode(500);
    }

    public function testFile()
    {
        $this->_prepareFolderTest();

        // The url should be url encoded.
        // TODO Use route.
        $url = '/repository/Folder_Test/' . 'image_Root_%231.png';

        // Check without buildiing.
        $this->dispatch($url);
        $this->assertResponseCode(404);

        $this->_requestUrl($url);
    }

    public function testFolder()
    {
        $this->_prepareFolderTest();

        $url = '/repository/' . 'Folder_Test.xml';
        $this->_requestUrl($url);

        $this->assertHeaderContains('Content-Type', 'text/xml');
    }

    protected function _requestUrl($url)
    {
        $folder = $this->_folder;

        // Check before buildiing.
        $folder->process(ArchiveFolder_Builder::TYPE_CHECK);
        $this->assertEquals(ArchiveFolder::STATUS_COMPLETED, $folder->status, 'Folder check failed: ' . $folder->messages);
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
        // $this->assertResponseCode(404);

        // Check after buildiing.
        $folder->process(ArchiveFolder_Builder::TYPE_UPDATE);
        $this->assertEquals(ArchiveFolder::STATUS_COMPLETED, $folder->status, 'Folder update failed: ' . $folder->messages);
        $this->dispatch($url);
        $this->assertResponseCode(200);
    }
}
