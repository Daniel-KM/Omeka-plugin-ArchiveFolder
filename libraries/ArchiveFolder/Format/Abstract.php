<?php
/**
 * Abstract class on which all other metadata format maps are based.
 *
 * @package ArchiveFolder
 */
abstract class ArchiveFolder_Format_Abstract
{
    // The xsi is required for each record according to oai-pmh protocol.
    const XSI_PREFIX = 'xsi';
    const XSI_NAMESPACE = 'http://www.w3.org/2001/XMLSchema-instance';

    // OAI prefix for the format represented by this class is requried.
    protected $_metadataPrefix;
    protected $_metadataSchema;
    protected $_metadataNamespace;

    // Tools that will be used.
    protected $_managePaths;

    // List of the simple Dublin Core terms, used when the user want to add
    // specific values to all records or when they can be get from files.
    // This is the default format of the metadata.
    protected $_dcTerms = array(
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

    // Parameters that depends on the format.
    protected $_parametersFormat = array(
        'use_dcterms' => false,
        'link_to_files' => false,
        'support_separated_files' => false,
        'compare_directly' => true,
    );

    // The base uri of the folder.
    protected $_uri;

    // Parameters that are set for a specific repository.
    protected $_parameters;

    // The writer is used to create the xml of the static repository.
    protected $_writer;

    // List of Qualified Dublin Core terms. This list is built automatically.
    protected $_dcmiTerms = array();

    // Current document to process.
    protected $_document;

    // Data that are used for each record.
    protected $_recordIdentifier;
    protected $_filepath;

    /**
     * The count of processed docs and files can be used to create unique ids
     * for a specific format, like Mets.
     */
    protected $_countDocuments = 0;
    protected $_countFiles = 0;
    protected $_countFilledRecords = 0;

    public function __construct($uri, $parameters)
    {
        $this->_uri = $uri;
        $this->_parameters = $parameters;

        if ($this->_parametersFormat['use_dcterms']) {
            $this->_loadDcmiElements();
        }

        $this->_managePaths = new ArchiveFolder_Tool_ManagePaths($uri, $parameters);
    }

    /**
     * Set the writer for the static repository.
     */
    public function setWriter($writer)
    {
        $this->_writer = $writer;
    }

    /**
     * Helper to load all DCMI elements from a file.
     */
    protected function _loadDcmiElements()
    {
        // Prepare labels of dc terms.
        require PLUGIN_DIR
            . DIRECTORY_SEPARATOR . 'ArchiveFolderDocument'
            . DIRECTORY_SEPARATOR . 'libraries'
            . DIRECTORY_SEPARATOR . 'elements_dcterms.php';
        foreach ($elements as $element) {
            $this->_dcmiTerms[$element['name']] = $element['label'];
        }
    }

    public function getMetadataPrefix()
    {
        return $this->_metadataPrefix;
    }

    /**
     * Get a parameter of the format by name.
     *
     * @return mixed Value, if any, else null.
     */
    public function getParameterFormat($name)
    {
        return isset($this->_parametersFormat[$name]) ? $this->_parametersFormat[$name] : null;
    }

    /**
     * Get parameter by name.
     *
     * @return mixed Value, if any, else null.
     */
    protected function _getParameter($name)
    {
        return isset($this->_parameters[$name]) ? $this->_parameters[$name] : null;
    }

    public function fillMetadataFormat()
    {
        $writer = $this->_writer;
        $writer->writeElement('oai:metadataPrefix', $this->_metadataPrefix);
        $writer->writeElement('oai:schema', $this->_metadataSchema);
        $writer->writeElement('oai:metadataNamespace', $this->_metadataNamespace);
    }

    /**
     * Create the xml element for the specified file, if needed.
     *
     * Metadata can be already created if there was a special file (xml) managed
     * by a mapping type.
     *
     * @param array $document Preprocessed document that contains identifiers
     * of record and the list of attached files.
     */
    public function fillRecord($document)
    {
        $this->_countDocuments++;
        $this->_countFiles += count($document['files']);
        $this->_countFilledRecords++;

        $this->_document = $document;

        // Prepare metadata if there are not set.
        if (!isset($this->_document['metadata'])) {
           $this->_addDefaultMetadata();
        }

        // Add default item type if not set.
        if (!isset($this->_document['metadata']['Dublin Core']['Type'])) {
           $this->_addItemType();
        }

        // Add identifier and relations to files if needed.
        $this->_addIdentifierAndRelationToFiles();

        $this->_fillMetadata();
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
        $this->_countFilledRecords++;

        // Prepare metadata if there are not set.
        if (!isset($file['metadata'])) {
            $this->_addDefaultFileMetadata($file, $order);
        }

        // Add identifier and relations to item if needed.
        $this->_addIdentifierAndRelationToItem($file, $order);

        $this->_fillMetadata($this->_document['files'][$order]);
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
    }

    /**
     * Create default metadata for a document without metadata (title).
     */
    protected function _addDefaultMetadata()
    {
        $doc = &$this->_document;

        $metadata = array();
        $metadata['Dublin Core']['Title'][] = $doc['name'] ?: '/';

        $doc['metadata'] = $metadata;
    }

    /**
     * Create default metadata for the specified file.
     *
     * @param array $file
     * @param integer $order
     */
    protected function _addDefaultFileMetadata($file, $order)
    {
        $doc = &$this->_document;

        $metadata = array();
        $metadata['Dublin Core']['Title'][] = pathinfo($file['path'], PATHINFO_BASENAME);

        $this->_document['files'][$order]['metadata'] = $metadata;
    }

    /**
     * Add the default item type if not set in the document metadata.
     */
    protected function _addItemType()
    {
        $doc = &$this->_document;

        $itemTypeName = $this->_getItemTypeName($doc);
        if ($itemTypeName) {
            if (!isset($doc['metadata']['Dublin Core']['Type'])
                    || !in_array($itemTypeName, $doc['metadata']['Dublin Core']['Type'])
                ) {
                $doc['metadata']['Dublin Core']['Type'][] = $itemTypeName;
            }
        }
    }

    /**
     * Add the identifier and the relations with files.
     */
    protected function _addIdentifierAndRelationToFiles()
    {
        $doc = &$this->_document;

        $url = $this->_managePaths->getAbsoluteUrl($doc['name']);
        if (empty($doc['metadata']['Dublin Core']['Identifier'])
                || !in_array($url, $doc['metadata']['Dublin Core']['Identifier'])
            ) {
            $doc['metadata']['Dublin Core']['Identifier'][] = $url;
        }

        if (!empty($this->_parametersFormat['link_to_files'])) {
            // Add metadata is different when item and files are separated.
            $recordsForFiles = (boolean) $this->_getParameter('records_for_files');
            if ($recordsForFiles) {
                $fileLink = $this->_parametersFormat['use_dcterms'] ? 'Requires' : 'Relation';
            }
            else {
                $fileLink = 'Identifier';
            }

            if (isset($doc['files'])) {
                foreach ($doc['files'] as $file) {
                    $url = $this->_managePaths->getAbsoluteUrl($file['name']);
                    if (empty($doc['metadata']['Dublin Core'][$fileLink])
                            || !in_array($url, $doc['metadata']['Dublin Core'][$fileLink])
                        ) {
                        $doc['metadata']['Dublin Core'][$fileLink][] = $url;
                    }
                }
            }
        }
    }

    /**
     * Create the xml element for the specified file.
     *
     * @param array $file
     * @param integer $order
     */
    protected function _addIdentifierAndRelationToItem($file, $order)
    {
        $doc = &$this->_document;

        $metadata = isset($file['metadata']) ? $file['metadata'] : array();

        $fileLink = $this->_parametersFormat['use_dcterms'] ? 'isRequiredBy' : 'Relation';
        $url = $this->_managePaths->getAbsoluteUrl($doc['name']);
        if (empty($metadata['Dublin Core'][$fileLink])
                || !in_array($url, $metadata['Dublin Core'][$fileLink])
            ) {
            $metadata['Dublin Core'][$fileLink][] = $url;
        }

        $this->_document['files'][$order]['metadata'] = $metadata;
    }

    /**
     * Fill metadata (Dublin Core and other ones) for a record (item or file).
     *
     * @param array $record
     * @return void
     */
    abstract protected function _fillMetadata($record = null);

    protected function _fillMetadataSet($metadata, $terms, $elementSetName, $prefix)
    {
        foreach ($terms as $name => $term) {
            if (isset($metadata[$elementSetName][$term])) {
                $elementName = $prefix . ':' . $name;
                foreach ($metadata[$elementSetName][$term] as $content) {
                    $this->_writeElement($elementName, $content);
                }
            }
        }
    }

    protected function _fillDublinCore($metadata)
    {
        // Simple Dublin Core.
        if (empty($this->_parametersFormat['use_dcterms'])) {
            return $this->_fillMetadataSet($metadata, $this->_dcTerms, 'Dublin Core', 'dc');
        }

        // Qualified Dublin Core.
        foreach ($this->_dcmiTerms as $name => $term) {
            if (isset($metadata['Dublin Core'][$term])) {
                if ($this->_parametersFormat['use_dcterms']) {
                    $prefixFormat = isset($this->_dcTerms[$name]) ? 'dc' : 'dcterms';
                }
                $elementName = $prefixFormat . ':' . $name;
                foreach ($metadata['Dublin Core'][$term] as $content) {
                    $this->_writeElement($elementName, $content);
                }
            }
        }
    }

    /**
     * Write a content with the xml writer
     *
     * @param string $elementName
     * @param string $string
     * @param array $attributes Optional attributes.
     * @param boolean $isRawXml If true, write raw xml, because string is
     * already escaped. It is useful specially to avoid escape of quotes for
     * json. This is automatically done if string is an xml one.
     * @return void
     */
    protected function _writeElement(
        $elementName,
        $string,
        $attributes = array(),
        $isRawXml = false
    ) {
        $writer = $this->_writer;

        if ($this->_isCdata($string)) {
            $string = substr($string, 9, strlen($string) - 12);
        }

        // TODO Check if cdata is needed (and add prefix if needed).
        // Check if the default prefix is managed or not.

        // writeRaw() is used instead of text() to avoid the useless escape of
        // quotes, that makes the base heavier. Quotes are automatically managed
        // by Zend during import and by Omeka in the theme.
        // Previously, Xml was protected by a cdata.
        if (!$isRawXml && !$this->_isXml($string)) {
            $string = htmlspecialchars($string, ENT_NOQUOTES);
        }

        $writer->startElement($elementName);
        if ($attributes) {
            $this->_writeAttributes($attributes);
        }
        $writer->writeRaw($string);
        $writer->endElement();
    }

    /**
     * Write attributes to an open element via xml writer.
     *
     * @param array $attributes Attributes.
     */
    protected function _writeAttributes($attributes)
    {
        $writer = $this->_writer;

        foreach ($attributes as $name => $value) {
            $writer->writeAttribute($name, $value);
        }
    }

    /**
     * Check if a string is an xml cdata one.
     *
     * @param string|SimpleXml $string
     * @return boolean
     */
    protected function _isCdata($string)
    {
        return strpos($string, '<![CDATA[') === 0 && strpos($string, ']]>') === strlen($string) - 3;
    }

    /**
     * Check if a string is an xml one.
     *
     * @param string $string
     * @return boolean
     */
    protected function _isXml($string)
    {
        return strpos($string, '<') !== false
            && strpos($string, '>') !== false
            // A main tag is added to allow inner ones.
            && (boolean) simplexml_load_string("<xml>$string</xml>", 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING);
    }

    /**
     * Get the item type according to the default file type or the first file.
     *
     * @return string|null The item type name.
     */
    protected function _getItemTypeName()
    {
        $doc = &$this->_document;

        $itemTypeName = $this->_getParameter('item_type_name');
        if ($itemTypeName == 'default') {
            $file = reset($doc['files']);
            $itemTypeName = $file
                ? $this->_getItemTypeNameFromFilename($file['path'])
                : null;
        }
        return $itemTypeName;
    }

    /**
     * Get the item type according to the file type.
     *
     * @todo Use getId3.
     *
     * @param string $filename Filename or filepath.
     * @return integer|null
     */
    private function _getItemTypeNameFromFilename($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        switch ($extension) {
            case 'gif':
            case 'jp2':
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'tiff':
                return 'Still Image';
            case 'doc':
            case 'docx':
            case 'odt':
            case 'pdf':
            case 'txt':
                return 'Text';
            case 'avi':
            case 'flv':
            case 'mov':
            case 'mp4':
                return 'Moving Image';
            case 'mp3':
            case 'wav':
                return 'Sound';
        }
    }

    /**
     * Get the md5 hash of a file.
     *
     * @param string $filepath Full filepath.
     * @return string
     */
    public function _md5hash($filepath)
    {
        return md5_file($filepath);
    }
}
