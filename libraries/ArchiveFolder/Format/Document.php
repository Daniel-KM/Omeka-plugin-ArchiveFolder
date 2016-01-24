<?php
/**
 * Metadata format map for the Document xml format.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Format_Document extends ArchiveFolder_Format_Abstract
{
    const METADATA_PREFIX = 'doc';
    const METADATA_SCHEMA = 'http://localhost/documents.xsd';
    const METADATA_NAMESPACE = 'http://localhost/documents/';

    protected $_metadataPrefix = self::METADATA_PREFIX;
    protected $_metadataSchema = self::METADATA_SCHEMA;
    protected $_metadataNamespace = self::METADATA_NAMESPACE;

    protected $_parametersFormat = array(
        'use_dcterms' => true,
        'link_to_files' => false,
        'support_separated_files' => false,
        'compare_directly' => true,
    );

    public function __construct($uri, $parameters)
    {
        if (empty($parameters['use_dcterms'])) {
            $this->_parametersFormat['use_dcterms'] = false;
        }

        parent::__construct($uri, $parameters);
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

        // Prepare the record.
        $writer->startElement('record');
        // No xmlns, because they are the same than the root.
        // $writer->writeAttribute('xmlns', self::METADATA_NAMESPACE);
        // $writer->writeAttribute('xmlns:' . self::XSI_PREFIX, self::XSI_NAMESPACE);
        // $writer->writeAttribute('xsi:schemaLocation', self::METADATA_NAMESPACE . ' ' . self::METADATA_SCHEMA);

        $this->_writeSpecific($record);
        $this->_writeMetadata($record);
        $this->_writeExtraData($record);

        $recordsForFiles = (boolean) $this->_getParameter('records_for_files');
        if ($recordsForFiles && isset($record['files'])) {
            foreach ($record['files'] as $file) {
                $writer->startElement('record');
                $this->_writeSpecific($file);
                $this->_writeMetadata($file);
                $this->_writeExtraData($file);
                $writer->endElement();
            }
        }

        $writer->endElement();
    }

    protected function _writeSpecific($record)
    {
        $writer = $this->_writer;

        if (empty($record['specific'])) {
            return;
        }

        foreach ($record['specific'] as $key => $value) {
            // Manage exceptions.
            // TODO Avoid this exception.
            if ($key == 'path' && $record['process']['record type'] == 'File') {
                $key = 'file';
                // The full path is checked, fully formatted and encoded.
                $value = $record['process']['fullpath'];
            }

            // Normalize keys like "original filename" or "item type".
            $key = Inflector::variablize($key);

            // Normally, only for tags.
            if (is_array($value)) {
                $value = implode(',', $value);
            }

            $writer->writeAttribute($key, $value);
        }
    }

    protected function _writeMetadata($record)
    {
        $writer = $this->_writer;

        if (empty($record['metadata'])) {
            return;
        }

        foreach ($record['metadata'] as $elementSetName => $elements) {
            $writer->startElement('elementSet');
            $writer->writeAttribute('name', $elementSetName);
            foreach ($elements as $elementName => $elementTexts) {
                $writer->startElement('element');
                $writer->writeAttribute('name', $elementName);
                foreach ($elementTexts as $elementText) {
                    $this->_writeElement('data', $elementText);
                }
                $writer->endElement();
            }
            $writer->endElement();
        }
    }

    protected function _writeExtraData($record)
    {
        $writer = $this->_writer;

        if (empty($record['extra'])) {
            return;
        }

        $writer->startElement('extra');
        foreach ($record['extra'] as $name => $field) {
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
