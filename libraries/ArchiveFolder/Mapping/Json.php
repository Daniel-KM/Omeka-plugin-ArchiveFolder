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

    /**
     * Convert one record (e.g. one row of a spreadsheet) into a document.
     *
     * @param var $record The record to process.
     * @param boolean $withSubRecords Add sub records if any (files...).
     * @return array The document.
     */
    protected function _getDocument($record, $withSubRecords)
    {
        $document = json_decode($record, true);
        if (!$withSubRecords) {
            unset($document['files']);
        }
        return $document;
    }
}
