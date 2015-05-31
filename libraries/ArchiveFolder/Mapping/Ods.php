<?php
/**
 * Map Open Document Spreadsheet into Omeka elements for each item and file.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Mapping_Ods extends ArchiveFolder_Mapping_Table
{
    protected $_checkMetadataFile = array('extension');
    protected $_extension = 'ods';

    /**
     * Prepare the list of documents set inside the current metadata file.
     */
    protected function _prepareDocuments()
    {
        $this->_processedFiles[$this->_metadataFilepath] = array();
        $documents = &$this->_processedFiles[$this->_metadataFilepath];

        // Reset the list of names: they are related to one file only.
        $this->_names = array();

        $content = $this->_extractZippedContent($this->_metadataFilepath, 'content.xml');
        if (empty($content)) {
            return;
        }

        // Get xml without errors and warnings.
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($xml === false) {
            return;
        }

        $tables = $this->_getArraysFromXml($xml);
        foreach ($tables as $table) {
            $this->_addDocumentsFromTable($table);
        }
    }

    /**
     * Return arrays of data from an xml.
     *
     * @param string $xml
     * @return array Cleaned array of arrays of data.
     */
    protected function _getArraysFromXml($xml)
    {
        // The content cannot be get directly, because extra spaces are encoded
        // and end of lines are needed. So some processes are needed.
        $xml->registerXPathNamespace('office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
        $xml->registerXPathNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
        $xml->registerXPathNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');

        $arrays = array();
        $xpath = '/office:document-content/office:body/office:spreadsheet/table:table';
        $tables = $xml->xpath($xpath);
        foreach ($tables as $table) {
            $array = array();

            $table->registerXPathNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
            $table->registerXPathNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');

            $xpath = 'table:table-row';
            $rows = $table->xpath($xpath);
            foreach ($rows as $row) {
                $currentRow = array();

                $row->registerXPathNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
                $row->registerXPathNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');

                $xpath = '@table:number-rows-repeated';
                $repeatedRows = $row->xpath($xpath);
                $repeatedRows = $repeatedRows ? (integer) reset($repeatedRows) : 1;

                $xpath = 'table:table-cell';
                $cells = $row->xpath($xpath);
                foreach ($cells as $cell) {
                    $text = '';

                    $cell->registerXPathNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
                    $cell->registerXPathNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');

                    $xpath = '@table:number-columns-repeated';
                    $repeatedColumns = $cell->xpath($xpath);
                    $repeatedColumns = $repeatedColumns ? (integer) reset($repeatedColumns) : 1;

                    // TODO Convert encoded spaces (<text:s text:c="2"/>) to true spaces.
                    $xpath = 'text:p';
                    $paragraphs = $cell->xpath($xpath);
                    foreach ($paragraphs as $paragraph) {
                        // __toString() is not used, because there can be
                        // sub-elements.
                        $text .= strip_tags($paragraph->saveXML());
                        $text .= $this->_endOfLine;
                    }
                    $text = trim($text);
                    for ($i = 1; $i <= $repeatedColumns; $i++) {
                        $currentRow[] = $text;
                    }
                }

                for ($i = 1; $i <= $repeatedRows; $i++) {
                    $array[] = $currentRow;
                }
            }

            $arrays[] = $array;
        }

        return $arrays;
    }
}
