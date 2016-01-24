<?php
/**
 * Metadata format map for the mets METS format.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Format_Mets extends ArchiveFolder_Format_Abstract
{
    const METADATA_PREFIX = 'mets';
    const METADATA_SCHEMA = 'http://www.loc.gov/standards/mets/mets.xsd';
    const METADATA_NAMESPACE = 'http://www.loc.gov/METS/';

    // Namespaces for simple and qualified Dublin Core.
    const DUBLIN_CORE_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    const DCTERMS_NAMESPACE = 'http://purl.org/dc/terms/';

    const XLINK_PREFIX = 'xlink';
    const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';

    protected $_metadataPrefix = self::METADATA_PREFIX;
    protected $_metadataSchema = self::METADATA_SCHEMA;
    protected $_metadataNamespace = self::METADATA_NAMESPACE;

    protected $_parametersFormat = array(
        'use_dcterms' => false,
        'link_to_files' => false,
        'support_separated_files' => false,
        'compare_directly' => false,
    );

    /**
     * The dmd and file ids are normalized, so the oai id can't be used.
     * Furthermore, they should be unique in all the instance. So default
     * formats are "DMD.doc[.file]" and  "original.doc.file". This is compliant
     * with the recommendation of the French National Library.
     * The 0 is added to avoids collisions when mixing documents with and
     * without Mets.
     */
    protected $_prefixDmd = 'DMD.0';
    protected $_prefixFile = 'original.0';
    protected $_separator = '.';

    public function __construct($uri, $parameters)
    {
        // Mets can use simple or qualified Dublin Core, so prepare it if
        // needed.
        if (!empty($parameters['use_dcterms'])) {
            $this->_parametersFormat['use_dcterms'] = true;
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

        // Prepare ids for record and files in all cases.
        $this->_prepareIds();

        // Prepare the mets record.
        $writer->startElement('mets');
        $writer->writeAttribute('xmlns', self::METADATA_NAMESPACE);
        $writer->writeAttribute('xmlns:' . self::XSI_PREFIX, self::XSI_NAMESPACE);
        $writer->writeAttribute('xsi:schemaLocation', self::METADATA_NAMESPACE . ' ' . self::METADATA_SCHEMA);
        $writer->writeAttribute('xmlns:' . self::XLINK_PREFIX, self::XLINK_NAMESPACE);

        // Fill all sections.
        $this->_fillSections();

        $writer->endElement();
    }

    /**
     * Prepare dmd and files ids.
     */
    protected function _prepareIds()
    {
        $dmdIndex = 0;
        $fileIndex = 0;
        $this->_document['dmdId'] = $this->_prefixDmd . $this->_countDocuments;
        $this->_document['fileId'] = '';
        foreach ($this->_document['files'] as $order => &$file) {
            $file['dmdId'] = $this->_prefixDmd . $this->_countDocuments . $this->_separator . ++$fileIndex;
            $file['fileId'] = $this->_prefixFile . $this->_countDocuments . $this->_separator . $fileIndex;
            // The DMD id must not be added to a file if it has no metadata.
            $file['used'] = false;
        }
    }

    /**
     * Fill all the sections of a record.
     */
    protected function _fillSections()
    {
        // 1. Mets Header.
        $this->_fillMetsHeader();

        // 2a. Descriptive Metadata for the record.
        $this->_fillDescriptiveMetadataSection();

        // 2b. Descriptive Metadata for each file if wanted.
        // By default, this format doesn't support separated files, so this can
        // be used only by sub-classes.
        $recordsForFiles = (boolean) $this->_getParameter('records_for_files');
        if ($recordsForFiles && isset($this->_document['files'])) {
            foreach ($this->_document['files'] as $order => &$file) {
                if (!isset($file['metadata'])) {
                    $this->_addDefaultFileMetadata($file, $order);
                }
                $this->_fillDescriptiveMetadataSection($file);
                $file['used'] = true;
            }
        }

        // 3. Administrative Metadata.
        $this->_fillAdministrativeMetadataSection();

        // 4. File section.
        $this->_fillFileSection();

        // 5. Structural Map.
        $this->_fillStructuralMap();

        // 6. Structural Links.
        $this->_fillStructuralLinks();

        // 7. Behavior.
        $this->_fillBehavior();
    }

    /**
     * Fill the Mets header.
     */
    protected function _fillMetsHeader()
    {
        $writer = $this->_writer;
        $doc = &$this->_document;

        $writer->startElement('metsHdr');
        $writer->writeAttribute('CREATEDATE', $this->_getParameter('timestamp'));
        // $writer->writeAttribute('LASTMODDATE', $this->_getParameter('timestamp'));
        // TODO Separate each emails.
        $emails = $this->_getParameter('admin_emails');
        $writer->startElement('agent');
        $writer->writeAttribute('ROLE', 'CREATOR');
        // $writer->writeAttribute('ROLE', 'ORGANIZATION');
        $writer->writeElement('name', $emails);
        $writer->writeElement('note', 'Archive Folder for Omeka');
        $writer->endElement();
        $writer->endElement();
    }

    /**
     * Set the descriptive metadata section of a record or a file.
     */
    protected function _fillDescriptiveMetadataSection($doc = null)
    {
        $writer = $this->_writer;

        if (empty($doc)) {
            $doc = &$this->_document;
        }

        $writer->startElement('dmdSec');
        $writer->writeAttribute('ID', $doc['dmdId']);

        $writer->startElement('mdWrap');
        $writer->writeAttribute('MIMETYPE', 'text/xml');
        $writer->writeAttribute('MDTYPE', 'DC');
        $writer->writeAttribute('LABEL', 'Dublin Core Metadata');

        // Fill the oai record with Dublin Core metadata.
        $writer->startElement('xmlData');
        $writer->writeAttribute('xmlns:dc', self::DUBLIN_CORE_NAMESPACE);
        if (!empty($this->_parametersFormat['use_dcterms'])) {
            $writer->writeAttribute('xmlns:dcterms', self::DCTERMS_NAMESPACE);
        }

        $this->_fillDublinCore($doc['metadata']);

        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
    }

    /**
     * Fill the administrative metadata section.
     */
    protected function _fillAdministrativeMetadataSection()
    {
        /*
        $writer = $this->_writer;
        $doc = &$this->_document;

        $writer->startElement('amdSec');
        $writer->startElement('techMD');
        $writer->endElement();
        $writer->startElement('rightsMD');
        $writer->endElement();
        $writer->startElement('sourceMD');
        $writer->endElement();
        $writer->startElement('digiprovMD');
        $writer->endElement();
        $writer->endElement();
        */
    }

    /**
     * Fill the file section.
     */
    protected function _fillFileSection()
    {
        $writer = $this->_writer;
        $doc = &$this->_document;

        $writer->startElement('fileSec');
        $writer->startElement('fileGrp');
        $writer->writeAttribute('USE', 'original');
        foreach ($doc['files'] as $order => $file) {
            $writer->startElement('file');
            $writer->writeAttribute('ID', $file['fileId']);
//            $this->_getFileInfo($file['path']);
            $writer->writeAttribute('MIMETYPE', 'image/jpeg');
            /*
            // TODO Determine the hash of the file.
            $hash = $this->_md5hash($file['path']);
            $writer->writeAttribute('CHECKSUM', $hash);
            $writer->writeAttribute('CHECKSUMTYPE', 'MD5');
            */
            if ($file['used']) {
                $writer->writeAttribute('DMDID', $file['dmdId']);
            }

            // Prepare the xlink.
            $title = $this->_getParameter('transfer_strategy') != 'Filesystem'
                 ? rawurldecode(pathinfo($file['path'], PATHINFO_BASENAME))
                 : pathinfo($file['path'], PATHINFO_BASENAME);
             $href = $this->_managePaths->getAbsoluteUrl($file['name']);

            $writer->startElement('FLocat');
            $writer->writeAttribute('LOCTYPE', 'URL');
            $writer->writeAttribute('xlink:type', 'simple');
            $writer->writeAttribute('xlink:title', $title);
            $writer->writeAttribute('xlink:href', $href);
            $writer->endElement();

            $writer->endElement();
        }
        $writer->endElement();
        $writer->endElement();
    }

    /**
     * Fill the structural map.
     */
    protected function _fillStructuralMap()
    {
        $writer = $this->_writer;
        $doc = &$this->_document;

        $writer->startElement('structMap');
        $writer->startElement('div');
        $writer->writeAttribute('DMDID', $this->_document['dmdId']);
        foreach ($doc['files'] as $order => $file) {
            $writer->startElement('fptr');
            $writer->writeAttribute('FILEID', $file['fileId']);
            $writer->endElement();
        }
        $writer->endElement();
        $writer->endElement();
    }

    /**
     * Fill the structural links.
     */
    protected function _fillStructuralLinks()
    {
    }

    /**
     * Fill the behavior.
     */
    protected function _fillBehavior()
    {
    }

    /**
     * Get a cleaned xml.
     *
     * In some cases, the format keep a time stamp inside content, so it can't
     * be compared directly.
     *
     * @param SimpleXml|string $xml
     * @return SimpleXml|string The cleaned xml.
     */
    public function cleanToCompare($xml)
    {
        $simpleXml = is_string($xml)
            ? simplexml_load_string($xml)
            : $xml;

        $metsHeader = dom_import_simplexml($simpleXml->metsHdr);
        if (!empty($metsHeader)) {
            $metsHeader->setAttribute('CREATEDATE', $this->_getParameter('timestamp'));
        }

        return is_string($xml)
            ? (string) $simpleXml
            : $simpleXml;
    }
}
