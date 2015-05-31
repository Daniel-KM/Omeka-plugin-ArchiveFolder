<?php
/**
 * Map Open Document Text files into Omeka elements for each item and file.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Mapping_Odt extends ArchiveFolder_Mapping_Text
{
    protected $_checkMetadataFile = array('extension');
    protected $_extension = 'odt';

    protected $_continueValue = '__';

    /**
     * Prepare the list of documents set inside the current metadata file.
     */
    protected function _prepareDocuments()
    {
        $this->_processedFiles[$this->_metadataFilepath] = array();
        $documents = &$this->_processedFiles[$this->_metadataFilepath];

        $content = $this->_extractZippedContent($this->_metadataFilepath, 'content.xml');
        if (empty($content)) {
            return;
        }

        // Get xml without errors and warnings.
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($xml === false) {
            return;
        }

        $content = $this->_getRawTextFromXml($xml);

        $lines = $this->_getLines($content);

        $this->_extractDocumentsFromLines($lines);
    }

    /**
     * Return the raw text from an xml.
     *
     * @param string $xml
     * @return string Cleaned raw text.
     */
    protected function _getRawTextFromXml($xml)
    {
        $content = '';

        // The content cannot be get directly, because extra spaces are encoded
        // and end of lines are needed. So some processes are needed.
        $xml->registerXPathNamespace('office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
        $xml->registerXPathNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');

        // TODO Convert encoded spaces (<text:s text:c="2"/>) to true spaces.

        $xpath = '/office:document-content/office:body/office:text/text:p';
        $paragraphs = $xml->xpath($xpath);
        foreach ($paragraphs as $paragraph) {
            // $paragraph->__toString() is not used, because there can be
            // sub-elements.
            $content .= strip_tags($paragraph->saveXML());
            $content .= $this->_endOfLine;
        }

        return $content;
    }
}
