<?php
class ArchiveFolder_IdentifierTest extends ArchiveFolder_Test_AppTestCase
{
    protected $_isAdminTest = true;

    protected $_identifiers = array();

    public function setUp()
    {
        parent::setUp();

        // Authenticate and set the current user.
        $this->user = $this->db->getTable('User')->find(1);
        $this->_authenticateUser($this->user);

        $this->_identifiers = $this->_getFiltered('archive_folder_oai_identifiers');
    }

    public function testIdentifiers()
    {
        $documents = array(
            // Test for an item.
            array(
                array(5 => array('name' => 'foo')),
            ),
            // Test for a file.
            array(
                array(10 => array('name' => 'foo')),
                array(20 => array('name' => 'bar', 'path' => 'http://example.org/foo/bar')),
            ),
        );

        $expecteds = array(
            'short_name' => array(':9gsktam27m2b6qc3f0qvct663', ':9w2ruxi1q72ussj1rd4ljt8gj'),
            'position_folder' => array(':5', ':10:20'),
            'position' => array(':1', ':2'),
            'hash_md5' => array('8b635db74f5b38930bcaf7c882623afb', '0ae250b2f914d555b897defc64f37a0c'),
            'hash_sha1' => array('2347cf2fcb31e6382318a3fdd7c69c97a98d2ac1', '236acf22539b7ec3c65fdf78372686c48ce868dd'),
        );

        foreach ($this->_identifiers as $prefix => $identifier) {
            $identifier = $identifier['class'];
            $identifier = new $identifier;
            $identifier->setFolderData('http://example.org/foo', array());

            foreach ($documents as $key => $data) {
                $oaiId = $identifier->create($data);
                if (isset($expecteds[$prefix][$key])) {
                    $this->assertEquals($expecteds[$prefix][$key], $oaiId,
                        __('Oai Identifier is not the same for prefix "%s" and record #%d.', $prefix, $key));
                }
                else {
                    $this->markTestIncomplete(
                        __('Test is not ready for prefix "%s".', $prefix)
                    );
                }
            }
        }
    }
}
