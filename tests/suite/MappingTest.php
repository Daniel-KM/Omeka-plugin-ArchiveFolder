<?php
class ArchiveFolder_MappingTest extends ArchiveFolder_Test_AppTestCase
{
    protected $_isAdminTest = true;

    protected $_mappings = array();

    public function setUp()
    {
        parent::setUp();

        // Authenticate and set the current user.
        $this->user = $this->db->getTable('User')->find(1);
        $this->_authenticateUser($this->user);

        $this->_mappings = $this->_getFiltered('archive_folder_mappings');
    }

    public function testMappings()
    {
        $notReady = array();
        foreach ($this->_metadataFilesByFolder as $folder => $metadataFiles) {
            foreach ($metadataFiles as $metadataFile) {
                $prefix = key($metadataFile);
                $metadataFile = reset($metadataFile);

                $mapping = $this->_mappings[$prefix]['class'];
                $uri = TEST_FILES_DIR . DIRECTORY_SEPARATOR . $folder;
                $mapping = new $mapping($uri, array(
                        // The ods file needs an element delimiter to process all tests.
                        'element_delimiter' => '|',
                ));

                $filepath = TEST_FILES_DIR
                    . DIRECTORY_SEPARATOR . $folder
                    . DIRECTORY_SEPARATOR . $metadataFile;

                $expectedPath = TEST_FILES_DIR
                    . DIRECTORY_SEPARATOR . 'Results'
                    . DIRECTORY_SEPARATOR . 'Mappings'
                    . DIRECTORY_SEPARATOR . basename($filepath) . '.json';

                if (!file_exists($expectedPath)) {
                    $notReady[] = basename($expectedPath);
                    continue;
                }

                $expected = file_get_contents($expectedPath);
                $expected = trim($expected);
                $this->assertTrue(strlen($expected) > 0, __('Result for file "%s" (prefix "%s") is not readable.', basename($filepath), $prefix));

                $result = $mapping->isMetadataFile($filepath);
                $this->assertTrue($result, __('The file "%s" is not recognized as format "%s".', basename($filepath), $prefix));

                $result = $mapping->listDocuments($filepath);
                $result = json_encode($result, true);
                // Remove local paths before comparaison.
                $jsonUri = trim(json_encode($uri), '"');
                $expected = str_replace($jsonUri, '::ExampleBasePath::', $expected);
                $result = str_replace($jsonUri, '::ExampleBasePath::', $result);
                $this->assertEquals($expected, $result, __('The list of documents for file "%s" (prefix "%s") is not correct.', basename($filepath), $prefix));
            }
        }

        if ($notReady) {
            $this->markTestIncomplete(
                __('Some file for the mapping test are not ready: "%s".', implode('", "', $notReady))
            );
        }
    }
}
