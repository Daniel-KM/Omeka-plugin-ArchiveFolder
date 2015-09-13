<?php
/**
 * Map json files into Omeka elements for each item and file.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Mapping_Json extends ArchiveFolder_Mapping_Abstract
{
    protected $_checkMetadataFile = array('extension', 'json');
    protected $_extension = 'json';

    /**
     * Prepare the list of documents set inside the current metadata file.
     */
    protected function _prepareDocuments()
    {
        $this->_processedFiles[$this->_metadataFilepath] = array();
        $documents = &$this->_processedFiles[$this->_metadataFilepath];

        $content = file_get_contents($this->_metadataFilepath);
        $documents = json_decode($content, true);
    }
}
