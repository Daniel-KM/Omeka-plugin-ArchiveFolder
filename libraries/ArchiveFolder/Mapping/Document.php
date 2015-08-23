<?php
/**
 * Map Document xml files into Omeka elements for each item and file.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Mapping_Document extends ArchiveFolder_Mapping_Abstract
{
    const XML_ROOT = 'documents';
    const XML_PREFIX = 'doc';
    const XML_NAMESPACE = 'http://localhost/documents/';

    const DC_PREFIX = 'dc';
    const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    const DC_TERMS_PREFIX = 'dcterms';
    const DC_TERMS_NAMESPACE = 'http://purl.org/dc/terms/';

    protected $_checkMetadataFile = array('extension', 'xml');
    protected $_extension = 'xml';
    protected $_formatXml = self::XML_PREFIX;
    protected $_xmlRoot = self::XML_ROOT;
    protected $_xmlNamespace = self::XML_NAMESPACE;

    // Current doc for internal purposes.
    protected $_doc;

    // List of fields that may be attributes of the item (can be in extra too).
    protected $_itemAttributes = array(
        'collection' => 'collection',
        'item type' => 'itemType',
        'featured' => 'featured',
        'public' => 'public',
        // These are special ones.
        'name' => 'name',
        'action' => 'action',
    );

    public function __construct($uri, $parameters)
    {
        // The use_qdc is forced to simplify process.
        $parameters['use_qdc'] = true;
        parent::__construct($uri, $parameters);
    }

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

        $this->_xml->registerXPathNamespace(self::XML_PREFIX, self::XML_NAMESPACE);

        $nameBase = $this->_getRelativePathToFolder($this->_metadataFilepath);
        foreach ($this->_xml->record as $key => $record) {
            // Process common metadata and create a new record for them.
            $doc = $this->_getDataForRecord($record);

            // Add a name.
            $doc['name'] = isset($doc['extra']['name'])
                ? reset($doc['extra']['name'])
                : $nameBase . '-' . ($key + 1);

            // Process files.
            $files = $record->record;
            foreach ($files as $fileXml) {
                $path = trim($this->_getXmlAttribute($fileXml, 'file'));
                // A filepath is needed.
                if (strlen($path) == 0) {
                    continue;
                }

                $file = $this->_getDataForRecord($fileXml);
                $file['path'] = $path;
                $doc['files'][$path] = $file;

                // The update of the xml with the good url is done now, but the
                // path in the document array is done later.
                // No check is done, because another one will be done later on
                // the document.
                $file = dom_import_simplexml($fileXml);
                if ($file) {
                    $fileurl = $this->_getRepositoryUrlForFile($path);
                    $file->setAttribute('file', $fileurl);
                }
            }

            // All records are imported: no check if empty.
            $recordDom = dom_import_simplexml($record);
            $recordDom->setAttribute('xmlns', self::XML_NAMESPACE);
            $doc['xml'] = $record->asXml();
            $documents[] = $doc;
        }
    }

    /**
     * Get all data for a record (item or file).
     *
     * @see ArchiveFolder_Harvest_Document::_getDataForRecord()
     *
     * @param SimpleXml $record
     * @return array The document array.
     */
    protected function _getDataForRecord($record)
    {
        $current = array();

        // Process flat Dublin Core.
        $record->registerXPathNamespace('', self::XML_NAMESPACE);
        $record->registerXPathNamespace(self::XML_PREFIX, self::XML_NAMESPACE);

        $record->registerXPathNamespace(self::DC_PREFIX, self::DC_NAMESPACE);
        $record->registerXPathNamespace(self::DC_TERMS_PREFIX, self::DC_TERMS_NAMESPACE);
        $xpath = 'dc:*|dcterms:*';
        $dcs = $record->xpath($xpath);
        foreach ($dcs as $dc) {
            $name = strtolower($dc->getName());
            if (isset($this->_dcTerms[$name])) {
                $text = $this->_innerXML($dc);
                $current['metadata']['Dublin Core'][$this->_dcTerms[$name]][] = $text;
            }
        }

        // The xml needs the Dublin Core namespaces in some cases.
        if (!empty($dcs)) {
            $recordDom = dom_import_simplexml($record);
            if ($recordDom) {
                $recordDom->setAttribute('xmlns:' . self::DC_TERMS_PREFIX, self::DC_TERMS_NAMESPACE);
                $recordDom->setAttribute('xmlns:' . self::DC_PREFIX, self::DC_NAMESPACE);
            }
        }

        // Process hierarchical elements.
        $elementSets = $record->elementSet;
        foreach ($elementSets as $elementSet) {
            $elementSetName = trim($this->_getXmlAttribute($elementSet, 'name'));
            // Unmanageable.
            if (strlen($elementSetName) == 0) {
                continue;
            }

            $elements = $elementSet->element;
            foreach ($elements as $element) {
                $elementName = trim($this->_getXmlAttribute($element, 'name'));
                // Unmanageable.
                if (strlen($elementName) == 0) {
                    continue;
                }

                $data = $element->data;
                foreach ($data as $value) {
                    $text = $this->_innerXML($value);
                    $current['metadata'][$elementSetName][$elementName][] = $text;
                }
            }
        }

        // Process special extra data.
        foreach ($this->_itemAttributes as $field => $xmlAttribute) {
            $data = trim($this->_getXmlAttribute($record, $xmlAttribute));
            if (strlen($data) > 0) {
                $current['extra'][$field][] = $data;
            }
        }

        // Process extra data.
        $extra = $record->extra;
        if (!empty($extra)) {
            $extraData = $extra->data;
            foreach ($extraData as $data) {
                $name = trim($this->_getXmlAttribute($data, 'name'));
                if (strlen($name) > 0) {
                    $text = $this->_innerXML($data);
                    $current['extra'][$name][] = $text;
                }
            }
        }

        // Normalize case of special extra data.
        foreach ($this->_itemAttributes as $field => $xmlAttribute) {
            $ucField = ucfirst($field);
            if (isset($current['extra'][$ucField])) {
                $current['extra'][$field] = isset($current['extra'][$field]) ? $current['extra'][$field] : array();
                $current['extra'][$field] = array_merge($current['extra'][$field], $current['extra'][$ucField]);
                unset($current['extra'][$ucField]);
            }
        }

        // Normalize "tags" (exception: can be tag, tags, Tag, or Tags).
        $tags = array();
        foreach (array('Tag', 'tag', 'Tags', 'tags') as $key) {
            if (isset($current['extra'][$key])) {
                $tags = array_merge($tags, $current['extra'][$key]);
                unset($current['extra'][$key]);
            }
        }
        if (!empty($tags)) {
            $current['extra']['tags'] = $tags;
        }

        return $current;
    }

    /**
     * Get the attribute of a xml element.
     *
     * @see ArchiveFolder_Harvest_Document::_getXmlAttribute()
     *
     * @param SimpleXml $xml
     * @param string $attribute
     * @return string|null
     */
    protected function _getXmlAttribute($xml, $attribute)
    {
        if (isset($xml[$attribute])) {
            return (string) $xml[$attribute];
        }
    }

    /**
     * Return the full inner content of an xml element.
     *
     * @todo Fully manage cdata
     *
     * @see ArchiveFolder_Harvest_Document::_innerXML()
     *
     * @param SimpleXml $xml
     * @return string
     */
    protected function _innerXML($xml)
    {
        $output = $xml->asXml();
        $pos = strpos($output, '>') + 1;
        $len = strrpos($output, '<') - $pos;
        return trim(substr($output, $pos, $len));
    }
}