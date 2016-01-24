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
    const DCTERMS_PREFIX = 'dcterms';
    const DCTERMS_NAMESPACE = 'http://purl.org/dc/terms/';

    protected $_checkMetadataFile = array('extension', 'xml');
    protected $_extension = 'xml';
    protected $_formatXml = self::XML_PREFIX;
    protected $_xmlRoot = self::XML_ROOT;
    protected $_xmlNamespace = self::XML_NAMESPACE;

    // Current doc for internal purposes.
    protected $_doc;

    // List of normalized special fields (attributes or extra data).
    // They are unique values, except tags.
    // Important: extra data can't start with these reserved terms, except if
    // they are not used together.
    // TODO Add a warn when special data and extra data start the same.
    protected $_specialData = array(
        // For any record (allow to manage process).
        'action' => true,
        'identifier field' => true,
        'record type' => true,
        'internal id' => true,
        'name' => true,
        // For files ("file" will be normalized as extra "path").
        'item' => true,
        'file' => true,
        'path' => true,
        'original filename' => true,
        'filename' => true,
        'md5' => true,
        'authentication' => true,
        // For items ("tag" will be normalized as extra "tags").
        'collection' => true,
        'item type' => true,
        'tag' => false,
        'tags' => false,
        // For items and collections.
        'featured' => true,
        'public' => true,
    );

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

        $nameBase = $this->_managePaths->getRelativePathToFolder($this->_metadataFilepath);
        foreach ($this->_xml->record as $key => $record) {
            $doc = $this->getDocument($record, true);

            // Add a name.
            $doc['process']['name'] = isset($doc['process']['name'])
                ? $doc['process']['name']
                : $nameBase . '-' . ($key + 1);

            // All records are imported: no check if empty.
            $recordDom = dom_import_simplexml($record);
            $recordDom->setAttribute('xmlns', self::XML_NAMESPACE);
            $doc['process']['xml'] = $record->asXml();
            $documents[] = $doc;
        }
    }

    /**
     * Convert one record (e.g. one row of a spreadsheet) into a document.
     *
     * @internal Here, the subrecords (files) require a path.
     *
     * @param SimpleXml $record The record to process.
     * @param boolean $withSubRecords Add sub records if any (files).
     * @return array The document.
     */
    protected function _getDocument($record, $withSubRecords)
    {
        // Process common metadata and create a new record for them.
        $doc = $this->_getDataForRecord($record);

        if ($withSubRecords) {
            // Process files.
            $files = $record->record;
            foreach ($files as $fileXml) {
                $file = $this->_getDataForRecord($fileXml);
                // A filepath is needed here.
                if (!isset($file['specific']['path']) || strlen($file['specific']['path']) == 0) {
                    continue;
                }

                $path = $file['specific']['path'];
                $doc['files'][$path] = $file;

                // The update of the xml with the good url is done now, but the
                // path in the document array is done later.
                // No check is done, because another one will be done later on
                // the document.
                $file = dom_import_simplexml($fileXml);
                if ($file) {
                    $fileurl = $this->_managePaths->getRepositoryUrlForFile($path);
                    $file->setAttribute('file', $fileurl);
                }
            }
        }

        return $doc;
    }

    /**
     * Get all data for a record (item or file).
     *
     * @see OaiPmhStaticRepository_Harvest_Document::_getDataForRecord()
     *
     * @param SimpleXml $record
     * @return array The document array.
     */
    protected function _getDataForRecord($record)
    {
        $current = array();

        // Set default values to avoid notices.
        $current['process'] = array();
        $current['specific'] = array();
        $current['metadata'] = array();
        $current['extra'] = array();

        // Process flat Dublin Core.
        $record->registerXPathNamespace('', self::XML_NAMESPACE);
        $record->registerXPathNamespace(self::XML_PREFIX, self::XML_NAMESPACE);

        $record->registerXPathNamespace(self::DC_PREFIX, self::DC_NAMESPACE);
        $record->registerXPathNamespace(self::DCTERMS_PREFIX, self::DCTERMS_NAMESPACE);
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
                $recordDom->setAttribute('xmlns:' . self::DCTERMS_PREFIX, self::DCTERMS_NAMESPACE);
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

        // Save all attributes as extra data.
        foreach ($record->attributes() as $name => $data) {
            $current['extra'][$name][] = (string) $data;
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

        // Normalize special data, keeping original order.
        // Filling data during loop is unpredictable.
        $extraLower = array();
        foreach ($current['extra'] as $field => $data) {
            $lowerField = $this->_spaceFromUppercase($field);
            if (isset($this->_specialData[$lowerField])) {
                // Only one value is allowed: keep last value.
                if ($this->_specialData[$lowerField]) {
                    $extraLower[$lowerField] = array_pop($data);
                }
                // Multiple values are allowed (for example tags). Keep order.
                else {
                    // Manage the tags exception (may be "tags" or "tag").
                    if ($lowerField == 'tag') {
                        $lowerField = 'tags';
                    }

                    $extraLower[$lowerField] = empty($extraLower[$lowerField])
                        ? $data
                        : array_merge($extraLower[$lowerField], $data);
                }
                unset($current['extra'][$field]);
            }
        }
        if ($extraLower) {
            $current['extra'] = array_merge($current['extra'], $extraLower);
        }

        // Exceptions.

        // Normalize "path" (exception: can be "file" or "path").
        if (isset($current['extra']['file'])) {
            $current['specific']['path'] = $current['extra']['file'];
            unset($current['extra']['file']);
        }

        // Normalize true extra data.
        if (!empty($current['extra'])) {
            $extraData = array_diff_key($current['extra'], $this->_specialData);
            if ($extraData) {
                // Step 1: set single value as string, else let it as array.
                $value = null;
                foreach ($extraData as $name => &$value) {
                    if (is_array($value)) {
                        // Normalize empty value.
                        if (count($value) == 0) {
                            $value = '';
                        }
                        // Make unique value a single string.
                        elseif (count($value) == 1) {
                            $value = reset($value);
                        }
                    }
                }
                // Required, because $value is a generic reference used just before.
                unset($value);

                // Step 2: Normalize extra data names like geolocation[latitude]
                // (array notation). They will be imported via a pseudo post.
                $extra = array();
                foreach ($extraData as $key => $value) {
                    $array = $this->_convertArrayNotation($key);
                    $array = $this->_nestArray($array, $value);
                    $value = reset($array);
                    $name = key($array);
                    $extra[] = array($name => $value);
                }
                $finalExtraData = array();
                foreach ($extra as $data) {
                    $finalExtraData = array_merge_recursive($finalExtraData, $data);
                }

                $specialData = array_intersect_key($current['extra'], $this->_specialData);
                $current['extra'] = array_merge($finalExtraData, $specialData);
            }
        }

        // Avoid useless metadata.
        unset($current['extra']['xmlns:' . self::DC_PREFIX]);
        unset($current['extra']['xmlns:' . self::DCTERMS_PREFIX]);

        return $current;
    }

    /**
     * Get the attribute of a xml element.
     *
     * @see OaiPmhStaticRepository_Harvest_Document::_getXmlAttribute()
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
     * @see OaiPmhStaticRepository_Harvest_Document::_innerXML()
     *
     * @param SimpleXml $xml
     * @return string
     */
    protected function _innerXML($xml)
    {
        $output = $xml->asXml();
        $pos = strpos($output, '>') + 1;
        $len = strrpos($output, '<') - $pos;
        $output = trim(substr($output, $pos, $len));

        // Only main CDATA is managed, not inside content: if this is an xml or
        // html, it will be managed automatically by the display; if this is a
        // text, the cdata is a text too.
        $simpleXml = simplexml_load_string($output, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
        // Non XML data.
        if (empty($simpleXml)) {
            // Check if this is a CDATA.
            if ($this->_isCdata($output)) {
                $output = substr($output, 9, strlen($output) - 12);
            }
            // Check if this is a json data.
            elseif (json_decode($output) !== null) {
                $output = html_entity_decode($output, ENT_NOQUOTES);
            }
            // Else this is a normal data.
            else {
                $output = html_entity_decode($output);
            }
        }
        // Else this is an xml value, so no change because it's xml escaped.

        return trim($output);
    }

    /**
     * Check if a string is an xml cdata one.
     *
     * @param string $string
     * @return boolean
     */
    protected function _isCdata($string)
    {
        $string = trim($string);
        return !empty($string)
            && strpos($string, '<![CDATA[') === 0 && strpos($string, ']]>') === strlen($string) - 3;
    }

    /**
     * Return an array of names from a string in array notation.
     */
    protected function _convertArrayNotation($string)
    {
        // Bail early if no array notation detected.
        if (!strstr($string, '[')) {
            $array = array($string);
        }
        // Convert array notation.
        else {
            if ('[]' == substr($string, -2)) {
                $string = substr($string, 0, strlen($string) - 2);
            }
            $string = str_replace(']', '', $string);
            $array = explode('[', $string);
        }
        return $array;
    }

    /**
     * Convert a flat array into a nested array via recursion.
     *
     * @param array $keys Flat array.
     * @param mixed $value The last value
     * @return array The nested array.
     */
    protected function _nestArray($keys, $value)
    {
       $nextKey = array_pop($keys);
       if (count($keys)) {
            $temp = array($nextKey => $value);
            return $this->_nestArray($keys, $temp);
        }
        return array($nextKey => $value);
    }

    /**
    * Converts a word as "spacedVersion" into "spaced version".
     *
     * @see Inflector::underscore()
     * @param string $string
     * @return string $string
     */
    static private function _spaceFromUppercase($string)
    {
        return  strtolower(preg_replace('/[^A-Z^a-z^0-9]+/', ' ',
        preg_replace('/([a-z\d])([A-Z])/', '\1 \2',
        preg_replace('/([A-Z]+)([A-Z][a-z])/', '\1 \2', $string))));
    }
}
