<?php

class ArchiveFolder_ImporterTest extends ArchiveFolder_Test_AppTestCase
{
    protected $_isAdminTest = true;

    public function setUp()
    {
        parent::setUp();

        // Authenticate and set the current user.
        $this->user = $this->db->getTable('User')->find(1);
        $this->_authenticateUser($this->user);
    }

    public function testFolder()
    {
        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test_Importer';
        $parameters = array();
        $this->_import($uri, $parameters, 12, 12, 12);
    }

    public function testCollections()
    {
        $uri = TEST_FILES_DIR
            . DIRECTORY_SEPARATOR . 'Folder_Test_Collections';
        $parameters = array();
        $this->_import($uri, $parameters, 7, 8, 8);
    }

    public function _import(
        $uri,
        $parameters,
        $totalImportedRecords,
        $totalArchiveRecords,
        $totalRecords
    ) {
        $folder = &$this->_folder;

        // The result is is checked via mappings.
        $this->_prepareFolderTest($uri, $parameters);
        $folder->process(ArchiveFolder_Builder::TYPE_CHECK);
        $this->assertEquals(ArchiveFolder_Folder::STATUS_COMPLETED, $folder->status, 'Folder check failed: ' . $folder->messages);

        $folder->process(ArchiveFolder_Builder::TYPE_UPDATE);
        $this->assertEquals(ArchiveFolder_Folder::STATUS_COMPLETED, $folder->status, 'Folder update failed: ' . $folder->messages);

        $xmlpath = $folder->getLocalRepositoryFilepath();

        $index = 1;
        do {
            $folder->import();
            $this->assertEquals(ArchiveFolder_Folder::STATUS_COMPLETED, $folder->status,
                'Folder process failed for record #' . $index . ': ' . $folder->messages);
            $index++;
        }
        while ($folder->countRecordsToImport()
            && !($folder->isError() || $folder->hasBeenStopped()));

        $this->assertEquals(0, $folder->countRecordsToImport());
        $this->assertEquals($totalImportedRecords, $folder->getParameter('imported_records'));

        $archiveRecords = $folder->getArchiveFolderRecords();
        $this->assertEquals($totalArchiveRecords, count($archiveRecords));

        $records = $folder->getRecords();
        $this->assertEquals($totalRecords, count($records));
    }
}
