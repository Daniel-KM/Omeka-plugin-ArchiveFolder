<?php
/**
 * Map Mets xml files into Omeka elements for each item and file.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Mapping_Mets extends ArchiveFolder_Mapping_Abstract
{
    const XML_ROOT = 'mets';
    const XML_PREFIX = 'mets';
    const XML_NAMESPACE = 'http://www.loc.gov/METS/';

    const DC_PREFIX = 'dc';
    const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';

    const XLINK_PREFIX = 'xlink';
    const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';

    protected $_checkMetadataFile = array('extension', 'validate xml');
    protected $_extension = 'xml';
    protected $_formatXml = self::XML_PREFIX;
    protected $_xmlRoot = self::XML_ROOT;
    protected $_xmlNamespace = self::XML_NAMESPACE;

    // Current doc for internal purposes.
    protected $_doc;

    /**
     * Prepare the list of documents set inside the current metadata file.
     */
    protected function _prepareDocuments()
    {
        $this->_processedFiles[$this->_metadataFilepath] = array();
        $documents = &$this->_processedFiles[$this->_metadataFilepath];

        // If the xml is too large, the php memory may be increased so it can be
        // processed directly via SimpleXml.
        $this->_xml = simplexml_load_file($this->_metadataFilepath);
        if ($this->_xml === false) {
            return;
        }

        // Only one document by mets file is managed (the main use of Mets).
        $doc = &$this->_doc;

        $this->_xml->registerXPathNamespace(self::XML_PREFIX, self::XML_NAMESPACE);
        $this->_xml->registerXPathNamespace(self::XLINK_PREFIX, self::XLINK_NAMESPACE);

        // Get the object id if any to create the name.
        $id = $this->_xml->xpath('/mets:mets/@OBJID[. != ""]');
        $name = $id ? $id[0]->__toString() : $this->_getRelativePathToFolder($this->_metadataFilepath);
        $doc = array('name' => $name);

        $this->_prepareMetadata();
        $this->_prepareFiles();
        $this->_prepareFilesMetadata();

        // All records are imported: no check if empty.
        $doc['xml'] = $this->_asXml();
        $documents[] = $doc;
    }

    /**
     * Prepare the metadata of the document.
     */
    protected function _prepareMetadata()
    {
        $doc = &$this->_doc;

        $dmdId = $this->_getDocumentMetadataId();
        $doc['metadata'] = $this->_getDCMetadata($dmdId) ?: array();
    }

    /**
     * Check the list of files and prepare the metadata of the record and files.
     */
    protected function _prepareFiles()
    {
        $doc = &$this->_doc;

        $referencedFiles = array();
        $xpath = '/mets:mets/mets:fileSec[1]//mets:fileGrp[1]/mets:file[mets:FLocat]';
        $xmlFiles = $this->_xml->xpath($xpath);
        foreach ($xmlFiles as $xmlFile) {
            $xmlFile->registerXPathNamespace(self::XML_PREFIX, self::XML_NAMESPACE);
            $xmlFile->registerXPathNamespace(self::XLINK_PREFIX, self::XLINK_NAMESPACE);
            $xpath = 'mets:FLocat[1]/@xlink:href';
            $result = $xmlFile->xpath($xpath);
            // This path can be absolute or relative.
            $path = (string) reset($result);

            // The update of the xml with the good url is done now, but the path
            // in the document array is done later.
            // No check is done, because another one will be done later on the
            // document.
            $flocat = dom_import_simplexml($xmlFile->FLocat);
            if ($flocat) {
                $fileurl = $this->_getRepositoryUrlForFile($path);
                $flocat->setAttribute('xlink:href', $fileurl);
            }

            // This id is used to simplify the fetch of the file metadata.
            $dmdId = (string) $xmlFile->attributes()->DMDID;
            // The dmd id can be set in files section or in the structural map,
            // else there is no metadata for this file.
            if (empty($dmdId)) {
                $fileId = (string) $xmlFile->attributes()->ID;
                $xpath = "/mets:mets/mets:structMap[1]//mets:div[mets:fptr[@FILEID = '$fileId']][1]/@DMDID";
                $result = $this->_xml->xpath($xpath);
                $dmdId = (string) reset($result);
            }

            $file = array();
            $file['path'] = $path;
            // The dmdId is used internally only.
            $file['dmdId'] = $dmdId;
            $referencedFiles[] = $file;
        }

        $doc['files'] = $referencedFiles;
    }

    /**
     * Prepare the list of metadata from the metadata file for each file.
     */
    protected function _prepareFilesMetadata()
    {
        $doc = &$this->_doc;

        foreach ($doc['files'] as &$file) {
            $dmdId = $file['dmdId'];
            $file['metadata'] = $this->_getDCMetadata($dmdId) ?: array();
            // The dmdId is used internally only.
            unset($file['dmdId']);
        }
    }

    /**
     * Return an array of DC metadata from an dmd id, when metadata are DC.
     *
     * @param string $dmdId
     * @return array|null
     */
    private function _getDCMetadata($dmdId)
    {
        // Ordered list of the simple Dublin Core terms.
        static $dcTerms = array(
            'title' => 'Title',
            'creator' => 'Creator',
            'subject' => 'Subject',
            'description' => 'Description',
            'publisher' => 'Publisher',
            'contributor' => 'Contributor',
            'date' => 'Date',
            'type' => 'Type',
            'format' => 'Format',
            'identifier' => 'Identifier',
            'source' => 'Source',
            'language' => 'Language',
            'relation' => 'Relation',
            'coverage' => 'Coverage',
            'rights' => 'Rights',
        );

        if (empty($dmdId)) {
            return;
        }

        $xpath = "/mets:mets/mets:dmdSec[@ID = '$dmdId']";
        $result = $this->_xml->xpath($xpath);
        if (empty($result)) {
            return;
        }
        $base = reset($result);

        $baseData = $base->mdWrap->xmlData->children(self::DC_NAMESPACE);
        // Quick convert xml to array.
        $dcMetadata = json_decode(json_encode($baseData), true);
        if (empty($dcMetadata)) {
            return;
        }
        $dcMetadata = reset($dcMetadata);

        $metadata = array();
        foreach ($dcTerms as $element => $name) {
            if (isset($dcMetadata[$element])) {
                $values = is_array($dcMetadata[$element])
                    ? $dcMetadata[$element]
                    : array($dcMetadata[$element]);
                $values = array_unique(array_filter(array_map('trim', $values)));
                if ($values) {
                    $metadata['Dublin Core'][$name] = $values;
                }
            }
        }

        return $metadata;
    }

    /**
     * Return the descriptive metadata id of the document.
     *
     * In some cases, specially images of a serial, the first descriptive
     * metadata describes something else (the journal) and only the second
     * describes the issue, that is needed here.
     *
     * @return string|null
     */
    private function _getDocumentMetadataId()
    {
        // When there is only one dmd section, this should be the document one.
        $xpath = '/mets:mets/mets:dmdSec';
        $result = $this->_xml->xpath($xpath);
        $countDmd = count($result);

        if (empty($countDmd)) {
            return null;
        }

        if ($countDmd == 1) {
            $xpath = '/mets:mets/mets:dmdSec[1]/@ID';
            $result = $this->_xml->xpath($xpath);
            return reset($result);
        }

        // More than one descriptive metadata.

        // If files have a div for themselves, it's the parent one, else this
        // the deepest div with all file pointers and a dmd id.
        // This is where the special case for serials is managed.
        $xpath = '/mets:mets/mets:fileSec[1]/mets:fileGrp[1]/mets:file';
        $result = $this->_xml->xpath($xpath);
        $countFiles = count($result);

        $xpath = "/mets:mets/mets:structMap[1]
            //mets:div[count(.//mets:fptr) = '$countFiles']/@DMDID";
        $result = $this->_xml->xpath($xpath);
        $dmdId = (string) array_pop($result);
        return $dmdId;
    }
}
