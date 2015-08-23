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
     * Check if the current file is a json one.
     *
     * @return boolean
     */
    protected function _checkJson()
    {
        $content = file_get_contents($this->_metadataFilepath);
        if (empty($content)) {
            return false;
        }

        $json = json_decode($content, true);
        return !is_null($json);
    }

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
