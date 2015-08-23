<?php
/**
 * Metadata format map for the Document xml format.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Format_Document extends ArchiveFolder_Format_Abstract
{
    const METADATA_PREFIX = 'doc';
    const METADATA_SCHEMA = 'http://localhost';
    const METADATA_NAMESPACE = 'http://localhost/documents/';

    protected $_metadataPrefix = self::METADATA_PREFIX;
    protected $_metadataSchema = self::METADATA_SCHEMA;
    protected $_metadataNamespace = self::METADATA_NAMESPACE;

    protected $_parametersFormat = array(
        'use_qdc' => true,
        'link_to_files' => false,
        'support_separated_files' => false,
        'compare_directly' => true,
    );

    // List of Qualified Dublin Core terms. This list is built automatically
    // if needed.
    protected $_dcmiTerms = array();

    // List of special extra fields that will be normalized.
    protected $_specialExtraData = array(
        'collection',
        'item type',
        'featured',
        'public',
        'name',
        'action',
        'tags',
    );

    public function __construct($parameters, $writer)
    {
        parent::__construct($parameters, $writer);

        // Mets can use simple or qualified Dublin Core, so prepare it if
        // needed.
        if (!empty($this->_getParameter('use_qdc'))) {
            $this->_parametersFormat['use_qdc'] = true;
            $this->_loadDcmiElements();
        }
    }

    /**
     * Create the xml element for the specified file for a main document.
     *
     * @internal The document should be loaded before use of this function.
     *
     * @param array $file
     * @param integer $order
     */
    public function fillFileAsRecord($file, $order)
    {
    }

    protected function _fillMetadata($record = null)
    {
        $writer = $this->_writer;

        // The record is the document because item and files are saved together.
        $record = $this->_document;

        // Prepare the mets record.
        $writer->startElement('record');
        $writer->writeAttribute('xmlns', self::METADATA_NAMESPACE);

        $this->_writeMetadata($record);
        $this->_writeExtraData($record);

        $recordsForFiles = (boolean) $this->_getParameter('records_for_files');
        if ($recordsForFiles && isset($record['files'])) {
            foreach ($record['files'] as $file) {
                $writer->startElement('record');
                // The filepath should be an absolute full url.
                $writer->writeAttribute('file', $file['path']);

                $this->_writeMetadata($file);
                $this->_writeExtraData($file);

                $writer->endElement();
            }
        }

        $writer->endElement();
    }

    protected function _writeMetadata($record)
    {
        $writer = $this->_writer;

        if (!isset($record['metadata'])) {
            return;
        }

        foreach ($record['metadata'] as $elementSetName => $elements) {
            $writer->startElement('elementSet');
            $writer->writeAttribute('name', $elementSetName);
            foreach ($elements as $elementName => $element) {
                $writer->startElement('element');
                $writer->writeAttribute('name', $elementName);
                foreach ($element as $data) {
                    $this->_writeElement('data', $data);
                }
                $writer->endElement();
            }
            $writer->endElement();
        }
    }

    protected function _writeExtraData($record)
    {
        $writer = $this->_writer;

        if (!isset($record['extra'])) {
            return;
        }

        $writer->startElement('extra');
        foreach ($record['extra'] as $name => $field) {
            // Normalize the name of special extra data.
            if (in_array(strtolower($name), $this->_specialExtraData)) {
                $name = strtolower($name);
            }
            if (is_string($field)) {
                $field = array($field);
            }
            // There is no intermediate field element: use metadata instead!
            foreach ($field as $data) {
                $this->_writeElement('data', $data, array('name' => $name));
            }
        }
        $writer->endElement();
    }
}
