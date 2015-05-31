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
            'short_name' => array(':a85lrkuyv4icmhbmni53h8qfs', ':4nis6eyfqeco5pzqisj5ngx82'),
            'position_folder' => array(':5', ':10:20'),
            'position' => array(':1', ':2'),
            'hash_md5' => array('118aeb43a76f4178e7de9ab46f12ee4b', 'c13d4094cd91bc63758bb2a643afaca5'),
            'hash_sha1' => array('e72ac88f201566e19109f35c6dd086365bc634b9', '38ef282a04cf110fa74709da632e8a740f1ade91'),
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
