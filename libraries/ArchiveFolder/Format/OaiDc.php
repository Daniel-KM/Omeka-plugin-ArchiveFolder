<?php
/**
 * Metadata format map for the required oai_dc Dublin Core format.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Format_OaiDc extends ArchiveFolder_Format_Abstract
{
    const METADATA_PREFIX = 'oai_dc';
    const METADATA_SCHEMA = 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd';
    const METADATA_NAMESPACE = 'http://www.openarchives.org/OAI/2.0/oai_dc/';

    // Namespaces for simple Dublin Core.
    const DUBLIN_CORE_NAMESPACE = 'http://purl.org/dc/elements/1.1/';

    protected $_metadataPrefix = self::METADATA_PREFIX;
    protected $_metadataSchema = self::METADATA_SCHEMA;
    protected $_metadataNamespace = self::METADATA_NAMESPACE;

    protected $_parametersFormat = array(
        'use_dcterms' => false,
        'link_to_files' => true,
        'support_separated_files' => true,
        'compare_directly' => true,
    );

    protected function _fillMetadata($record = null)
    {
        $writer = $this->_writer;

        if (is_null($record)) {
            $record = $this->_document;
        }

        // Prepare the oai record.
        $writer->startElement('oai_dc:dc');
        $writer->writeAttribute('xmlns:oai_dc', self::METADATA_NAMESPACE);
        $writer->writeAttribute('xmlns:dc', self::DUBLIN_CORE_NAMESPACE);
        $writer->writeAttribute('xmlns:' . self::XSI_PREFIX, self::XSI_NAMESPACE);
        $writer->writeAttribute('xsi:schemaLocation', self::METADATA_NAMESPACE . ' ' . self::METADATA_SCHEMA);

        $this->_fillDublinCore($record['metadata']);

        $writer->endElement();
    }
}
