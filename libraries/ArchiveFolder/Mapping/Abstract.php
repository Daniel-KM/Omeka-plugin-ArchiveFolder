<?php
/**
 * Map metadata files into Omeka elements for each item and file.
 *
 * If the record contains an xml file, it will be copied directly in the xml of
 * the folder for the record if its format is managed, like Mets. So there won't
 * be intermediate conversions before the harvest, so this is the recommended
 * way to import metadata if they aren't Dublin Core. So this class is mainly
 * used to create the required default oai_dc format, the only managed metadata.
 *
 * File paths or urls are not checked: it will be done by the harvester.
 *
 * Checks on Dublin Core elements can be made here, but another one is done
 * during formatting.
 *
 * @package ArchiveFolder
 */
abstract class ArchiveFolder_Mapping_Abstract
{
    // The xsi is required for each record according to oai-pmh protocol.
    const XSI_PREFIX = 'xsi';
    const XSI_NAMESPACE = 'http://www.w3.org/2001/XMLSchema-instance';

    protected $_uri;
    protected $_parameters;

    // Tools that will be used.
    protected $_managePaths;
    protected $_validateFile;
    protected $_processXslt;

    // The processed metadata for each file path, to avoid two processes.
    protected $_processedFiles = array();

    // The full path to current metadata file.
    protected $_metadataFilepath;

    // The list of tests to check if a file is a metadata file.
    protected $_checkMetadataFile = array('false');

    // The lower case extension, to check if the file is a metadata one.
    protected $_extension;

    /**
     * When the source contains an xml file, it can be copied directly in the
     * xml of the record for the specified format. It can be changed before to
     * remove or to add some elements.
     * These variables are only used by xml formats.
     */
    // The format to add to.
    protected $_formatXml;
    protected $_xmlRoot = '';
    protected $_xmlNamespace = '';
    // The content of the file via SimpleXML.
    protected $_xml;

    // List of the Dublin Core terms. Can be enlarged to qualified ones.
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

    // List of normalized special fields (attributes or extra data).
    // They are unique values, except tags.
    protected $_specialData = array(
        // For any record (allow to manage process).
        'record type' => false,
        'action' => false,
        'name' => false,
        'identifier field' => false,
        'internal id' => false,
        // For files ("file" will be normalized as speciic "path").
        'item' => false,
        'file' => false,
        'path' => false,
        'original filename' => false,
        'filename' => false,
        'md5' => false,
        'authentication' => false,
        // For items ("tag" will be normalized as specific "tags").
        'collection' => false,
        'item type' => false,
        'tag' => true,
        'tags' => true,
        // For items and collections.
        'featured' => false,
        'public' => false,
    );

    // Element separator is used for the name of the element for some formats.
    protected $_elementNameSeparator = ':';
    protected $_endOfLine = PHP_EOL;

    /**
     * Constructor of the class.
     *
     * @param string $uri The uri of the folder.
     * @param array $parameters The parameters to use for the mapping.
     * @return void
     */
    public function __construct($uri, array $parameters)
    {
        $this->_uri = $uri;
        $this->_parameters = $parameters;

        $this->_managePaths = new ArchiveFolder_Tool_ManagePaths($this->_uri, $this->_parameters);
        $this->_validateFile = new ArchiveFolder_Tool_ValidateFile();
        $this->_processXslt = new ArchiveFolder_Tool_ProcessXslt();

        $this->_elementNameSeparator = $this->_getParameter('element_name_separator') ?: ':';

        // Prepare labels of dc terms. Dublin Core Terms can be always checked
        // with the mapping process (method "getDataName()").
        require PLUGIN_DIR
            . DIRECTORY_SEPARATOR . 'ArchiveFolder'
            . DIRECTORY_SEPARATOR . 'libraries'
            . DIRECTORY_SEPARATOR . 'ArchiveFolder'
            . DIRECTORY_SEPARATOR . 'elements_dcterms.php';
        $this->_dcTerms = array();
        foreach ($elements as $element) {
            // Checks are done on lower case names and labels.
            $this->_dcTerms[strtolower($element['name'])] = $element['label'];
            $this->_dcTerms[strtolower($element['label'])] = $element['label'];
        }
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

    /**
     * Check if the a file is a metadata file via extension and/or content.
     *
     * @param string $filepath The path to the metadata file.
     * @return boolean
     */
    public function isMetadataFile($filepath)
    {
        return $this->_validateFile->isMetadataFile(
            $filepath,
            $this->_checkMetadataFile,
            array(
                'extension' => $this->_extension,
                'xmlRoot' => $this->_xmlRoot,
                'xmlNamespace' => $this->_xmlNamespace,
        ));
    }

    /**
     * List items and attached files in the current metadata file.
     *
     * @param string $filepath The path to the metadata file.
     * @return array Stored documents.
     */
    public function listDocuments($filepath)
    {
        if (!isset($this->_processedFiles[$filepath])) {
            $this->_metadataFilepath = $filepath;
            $this->_managePaths->setMetadataFilepath($filepath);
            $this->_prepareDocuments();
            $this->_setXmlFormat();
            $this->_validateDocuments();
            $this->_removeDuplicateMetadata();
        }
        return $this->_processedFiles[$filepath];
    }

    /**
     * Convert one record (e.g. one row of a spreadsheet) into a document.
     *
     * @internal Currently, this is used only with the Archive Document.
     *
     * @param var $record The record to process.
     * @param boolean $withSubRecords Add sub records if any (files...).
     * @return array The document.
     */
    public function getDocument($record, $withSubRecords = false)
    {
        $document = $this->_getDocument($record, $withSubRecords);
        if (empty($document)) {
            return array();
        }
        $document = $this->_normalizeDocument($document);
        return $document;
    }

    /**
     * If the source is xml, return the format in order to append it directly.
     *
     * @return string
     */
    public function getFormatXml()
    {
        return $this->_formatXml;
    }

    /**
     * If the source is xml, return it, eventually modified, without the xml
     * declaration.
     *
     * @internal Internal uris should be the final ones (relative or absolute).
     * The namespaces and schema location should be set.
     * The namespace for "xsi" is automatically added here if needed.
     *
     * @return string|null
     */
    protected function _asXml()
    {
        if (empty($this->_formatXml)) {
            return;
        }

        // The xml should be built in a previous step.
        if (empty($this->_xml)) {
            return;
        }

        // Add the "xsi" namespace, standard in the oai-pmh protocol.
        $dom = dom_import_simplexml($this->_xml)->ownerDocument;
        if ($dom === false) {
            return;
        }
        $dom->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . self::XSI_PREFIX, self::XSI_NAMESPACE);
        $this->_xml = simplexml_import_dom($dom);

        // Return without the xml declaration.
        return $dom->saveXML($dom->documentElement);
    }

    /**
     * Prepare the list of documents set inside the current metadata file.
     */
    abstract protected function _prepareDocuments();

    /**
     * Convert one record (e.g. one row of a spreadsheet) into a document.
     *
     * @internal Currently, this is used only with the Archive Document.
     *
     * @param var $record The record to process.
     * @param boolean $withSubRecords Add sub records if any (files...).
     * @return array The document.
     */
    protected function _getDocument($record, $withSubRecords)
    {
    }

    /**
     * Validate documents, secure paths of files and make them absolute.
     *
     * @internal Only local filepaths are checked.
     */
    protected function _validateDocuments()
    {
        $documents = &$this->_processedFiles[$this->_metadataFilepath];
        // Check file paths and names (if one is absent, the other is used).
        $nameBase = $this->_managePaths->getRelativePathToFolder($this->_metadataFilepath);
        foreach ($documents as $key => &$document) {
            $document = $this->_normalizeDocument($document, 'Item');
            // Check if the document is empty.
            if (empty($document['specific'])
                    && empty($document['metadata'])
                    && empty($document['extra'])
                    && empty($document['files'])
                ) {
                // Special check for process: remove xml, automatically added.
                $check = array_diff_key($document['process'], array('xml' => true, 'format_xml' => true));
                if (empty($check)) {
                    unset($documents[$key]);
                    continue;
                }
            }

            // Add an internal name if needed.
            // Warning: this should not be the same than the one defined inside
            // a metadata file, even if the issue is very rare. Nevertheless, it
            // should be enough stable to be updatable in main normal cases.
            if (empty($document['process']['name'])) {
                $document['process']['name'] = $nameBase . ':0' . ($key + 1);
            }

            if (empty($document['files'])) {
                continue;
            }
            foreach ($document['files'] as $order => &$file) {
                $file = $this->_normalizeDocument($file, 'File');

                // The absolute and the relative paths should be the same file.
                $path = isset($file['specific']['path']) && strlen($file['specific']['path']) > 0
                    ? $file['specific']['path']
                    : (isset($file['process']['name']) ? $file['process']['name'] : null);

                // Check if there is a filepath.
                // Empty() is not used, because "0" can be a content.
                $path = trim($path);
                if (strlen($path) == 0) {
                    throw new ArchiveFolder_BuilderException(__('The filepath for document "%s" is empty.', $document['process']['name']));
                }

                // The path is absolute or relative to the path of the
                // metadata file.
                $absoluteFilePath = $this->_managePaths->getAbsolutePath($path);
                if (empty($absoluteFilePath)) {
                    throw new ArchiveFolder_BuilderException(__('The file "%s" is incorrect.', $path));
                }

                // No relative path if the file is external to the folder.
                $relativeFilepath = $this->_managePaths->isInsideFolder($absoluteFilePath)
                    ? $this->_managePaths->getRelativePathToFolder($path)
                    : $absoluteFilePath;
                if (empty($relativeFilepath)) {
                    throw new ArchiveFolder_BuilderException(__('The file path "%s" is incorrect.', $path));
                }

                $file['process']['name'] = $relativeFilepath;
                $file['specific']['path'] = $absoluteFilePath;
            }
        }

        return $documents;
    }

    /**
     * Check and normalize a document (move extra data in process and specific).
     *
     * No default is added here, except the record type.
     *
     * @param array $document The document to normalize.
     * @param array $recordType Optinoal The record type if not set
     * @return array The normalized document.
     */
    protected function _normalizeDocument($document, $recordType = null)
    {
        // Set default values to avoid notices.
        if (!isset($document['process'])) {
            $document['process'] = array();
        }
        if (!isset($document['specific'])) {
            $document['specific'] = array();
        }
        if (!isset($document['metadata'])) {
            $document['metadata'] = array();
        }
        if (!isset($document['extra'])) {
            $document['extra'] = array();
        }

        // Normalization for any record.
        $process = array(
            'record type' => null,
            'action' => null,
            'name' => null,
            'identifier field' => null,
            'internal id' => null,
            'format_xml' => null,
            'xml' => null,
        );
        $document['process'] = array_intersect_key(
            array_merge($document['extra'], $document['process']),
            $process);
        $document['extra'] = array_diff_key($document['extra'], $process);

        // For compatibility, the name can be set at root of the document.
        if (isset($document['name'])) {
            $document['process']['name'] = $document['name'];
            unset($document['name']);
        }

        // Set the record type, one of the most important value.
        if (empty($document['process']['record type'])) {
            // When the record type is set directly, it is used.
            if ($recordType) {
                $document['process']['record type'] = $recordType;
            }

            // Force the record type to item if not a file.
            else {
                $document['process']['record type'] = !isset($document['specific']['files'])
                        && (isset($document['specific']['path'])
                            || isset($document['extra']['path'])
                            || isset($document['path'])
                        )
                    ? 'File'
                    : 'Item';
            }
        }
        // Normalize and check the record type.
        $recordType = ucfirst(strtolower($document['process']['record type']));
        if (!in_array($recordType, array('File', 'Item', 'Collection'))) {
            throw new ArchiveFolder_BuilderException(__('The record type "%s" does not exist.',
                $document['extra']['record type']));
        }
        $document['process']['record type'] = $recordType;

        // Check the action.
        if (!empty($document['process']['action'])) {
            $action = strtolower($document['process']['action']);
            if (!in_array($action, array(
                    ArchiveFolder_Importer::ACTION_UPDATE_ELSE_CREATE,
                    ArchiveFolder_Importer::ACTION_CREATE,
                    ArchiveFolder_Importer::ACTION_UPDATE,
                    ArchiveFolder_Importer::ACTION_ADD,
                    ArchiveFolder_Importer::ACTION_REPLACE,
                    ArchiveFolder_Importer::ACTION_DELETE,
                    ArchiveFolder_Importer::ACTION_SKIP,
                ))) {
                $message = __('The action "%s" does not exist.', $document['process']['action']);
                throw new ArchiveFolder_ImporterException($message);
            }
            $document['process']['action'] = $action;
        }

        // Specific normalization according to the record type: separate Omeka
        // metadata and element texts, that are standard metadata.
        switch ($document['process']['record type']) {
            case 'File':
                $specific = array(
                    'path' => null,
                    // "fullpath" is automatically checked and defined below.
                    'original filename' => null,
                    'filename' => null,
                    'md5' => null,
                    'authentication' => null,
                );
                $document['specific'] = array_intersect_key(
                    array_merge($document['extra'], $document['specific']),
                    $specific);
                $document['extra'] = array_diff_key($document['extra'], $specific);

                if (empty($document['specific']['path'])) {
                    $document['specific']['path'] = empty($document['path']) ? '' : $document['path'];
                }

                // The full path is checked and simplifies management of files.
                if ($document['specific']['path']) {
                    $absoluteFilePath = $this->_managePaths->getAbsoluteUri($document['specific']['path']);
                    // An empty result means an incorrect path.
                    // Access rights for local files are checked by the builder.
                    if (empty($absoluteFilePath)) {
                        $message = __('The path "%s" is forbidden or incorrect.', $document['specific']['path']);
                        throw new ArchiveFolder_BuilderException($message);
                    }
                    $document['process']['fullpath'] = $absoluteFilePath;
                }
                // No path is allowed for update if there is another identifier.
                else {
                    $document['process']['fullpath'] = '';
                }

                // The authentication is kept if md5 is set too.
                if (!isset($document['specific']['authentication']) && !empty($document['specific']['md5'])) {
                    $document['specific']['authentication'] = $document['specific']['md5'];
                }
                unset($document['specific']['md5']);
                break;

            case 'Item':
                $specific = array(
                    Builder_Item::IS_PUBLIC => null,
                    Builder_Item::IS_FEATURED => null,
                    Builder_Item::COLLECTION_ID => null,
                    Builder_Item::ITEM_TYPE_ID => null,
                    Builder_Item::ITEM_TYPE_NAME => null,
                    Builder_Item::TAGS => null,
                    'collection' => null,
                    'item type' => null,
                );
                $document['specific'] = array_intersect_key(
                    array_merge($document['extra'], $document['specific']),
                    $specific);
                $document['extra'] = array_diff_key($document['extra'], $specific);

                // Check the collection.
                if (isset($document['specific'][Builder_Item::COLLECTION_ID])
                        && isset($document['specific']['collection'])
                    ) {
                    unset($document['specific'][Builder_Item::COLLECTION_ID]);
                }

                // No collection name, so check the collection id.
                if (isset($document['specific'][Builder_Item::COLLECTION_ID])) {
                    // If empty, collection id should be null, not "" or "0".
                    if (empty($document['specific'][Builder_Item::COLLECTION_ID])) {
                        $document['specific'][Builder_Item::COLLECTION_ID] = null;
                    }
                    // Check the collection id.
                    else {
                        $collection = get_db()->getTable('Collection')->find($document['specific'][Builder_Item::COLLECTION_ID]);
                        if (empty($collection)) {
                            $message = __('The collection "%s" does not exist.', $document['specific'][Builder_Item::COLLECTION_ID]);
                            throw new ArchiveFolder_BuilderException($message);
                        }
                    }
                }

                // Check the item type, that can be set as "item_type_id",
                // "item_type_name" and "item type". The "item type" is kept
                // with the key "item_type_name".
                if (isset($document['specific']['item type'])) {
                    unset($document['specific'][Builder_Item::ITEM_TYPE_ID]);
                    $document['specific'][Builder_Item::ITEM_TYPE_NAME] = $document['specific']['item type'];
                    unset($document['specific']['item type']);
                }
                // Item type name is used if no item type.
                elseif (isset($document['specific'][Builder_Item::ITEM_TYPE_NAME])) {
                    unset($document['specific'][Builder_Item::ITEM_TYPE_ID]);
                }

                $itemTypes = get_db()->getTable('ItemType')->findPairsForSelectForm();
                // Check the item type name.
                if (!empty($document['specific'][Builder_Item::ITEM_TYPE_NAME])) {
                    $itemTypeId = array_search(strtolower($document['specific'][Builder_Item::ITEM_TYPE_NAME]), array_map('strtolower', $itemTypes));
                    if (!$itemTypeId) {
                        throw new ArchiveFolder_BuilderException(__('The item type "%s" does not exist.',
                            $document['specific'][Builder_Item::ITEM_TYPE_NAME]));
                    }
                    $document['specific'][Builder_Item::ITEM_TYPE_NAME] = $itemTypes[$itemTypeId];
                }

                // Check the item type id.
                elseif (!empty($document['specific'][Builder_Item::ITEM_TYPE_ID])) {
                    if (!isset($itemTypes[$itemTypeId])) {
                        throw new ArchiveFolder_BuilderException(__('The item type id "%d" does not exist.',
                            $itemTypeId));
                    }
                    unset($document['specific'][Builder_Item::ITEM_TYPE_ID]);
                    $document['specific'][Builder_Item::ITEM_TYPE_NAME] = $itemTypes[$itemTypeId];
                }
                break;

            case 'Collection':
                $specific = array(
                    Builder_Collection::IS_PUBLIC => null,
                    Builder_Collection::IS_FEATURED => null,
                );
                $document['specific'] = array_intersect_key(
                    array_merge($document['extra'], $document['specific']),
                    $specific);
                $document['extra'] = array_diff_key($document['extra'], $specific);
                break;
        }

        // Normalize the identifier field if it is a special one.
        if (!empty($document['process']['identifier field'])) {
            $lowerIdentifierField = str_replace('_', ' ', strtolower($document['process']['identifier field']));
            if (in_array($lowerIdentifierField, array(
                    // For any record.
                    ArchiveFolder_Importer::IDFIELD_NONE,
                    ArchiveFolder_Importer::IDFIELD_INTERNAL_ID,
                    // For file only.
                    'original filename',
                    'filename',
                    'md5',
                    'authentication',
                ))) {

                if ($document['process']['record type'] == 'File') {
                    if ($lowerIdentifierField == 'original filename') {
                        $lowerIdentifierField = 'original_filename';
                    }
                    elseif ($lowerIdentifierField == 'md5') {
                        $lowerIdentifierField = 'authentication';
                    }
                }
                elseif (in_array($lowerIdentifierField, array(
                        'original filename',
                        'filename',
                        'md5',
                        'authentication',
                    ))) {
                    $message = __('The identifier field "%s" is not allowed for the record type "%s".',
                        $document['process']['identifier field'], $document['process']['record type']);
                    throw new ArchiveFolder_BuilderException($message);
                }

                $document['process']['identifier field'] = $lowerIdentifierField;
            }
        }

        // The identifier itself is checked only during import.

        // Clean value for any record (done above).
        // Clean specific value of fIle.
        unset($document['path']);
        unset($document['extra']['path']);
        unset($document['extra']['original filename']);
        unset($document['extra']['filename']);
        unset($document['extra']['md5']);
        unset($document['extra']['authentication']);
        // Clean specific value of item.
        unset($document['extra']['collection']);
        unset($document['extra']['collection_id']);
        unset($document['extra']['item_type_id']);
        unset($document['extra']['item_type_name']);
        unset($document['extra']['item type']);
        unset($document['extra']['tags']);
        // Clean specific value of item and collection.
        unset($document['extra']['public']);
        unset($document['extra']['featured']);

        // Normalize the element texts.
        // Remove the Omeka 'html', that slows down process and that can be
        // determined automatically when it is really needed.
        foreach ($document['metadata'] as $elementSetName => &$elements) {
            foreach ($elements as $elementName => &$elementTexts) {
                foreach ($elementTexts as &$elementText) {
                    if (is_array($elementText)) {
                        $elementText = $elementText['text'];
                    }
                }
                // Trim the metadata too to avoid useless spaces.
                $elementTexts = array_map('trim', $elementTexts);
            }
        }

        return $document;
    }

    /**
     * Get the data or element set and element name from a string.
     *
     * @param string $string The string to identify and clean.
     * @return string|array|null If recognized, the array with element set name
     * and the element name, else the cleaned string, else null.
     */
    protected function _getDataName($string)
    {
        $name = trim($string);

        // If no name, this is a comment.
        if (strlen($name) == 0) {
            return null;
        }

        // Prepare element.
        $elementSetName = '';
        $elementName = '';

        $name = trim(trim($name, $this->_elementNameSeparator . ' '));
        $posSepareElement = mb_strpos($name, $this->_elementNameSeparator);
        if (empty($posSepareElement)) {
            $lowerName = strtolower($name);

            // Manage special headers.
            if (isset($this->_specialData[$lowerName])) {
                return $lowerName;
            }

            if (isset($this->_dcTerms[$lowerName])) {
                $elementSetName = 'Dublin Core';
                $elementName = $this->_dcTerms[$lowerName];
            }
            // Empty element set name (extra data).
            else {
                $elementName = $name;
            }
        }
        // Full element.
        else {
            $elementSetName = trim(trim(mb_substr($name, 0, $posSepareElement), $this->_elementNameSeparator . ' '));
            $elementName = trim(trim(mb_substr($name, $posSepareElement), $this->_elementNameSeparator . ' '));
        }

        // Check the field name. If none, this is a comment.
        if (empty($elementName)) {
            return null;
        }

        // Save the element name.
        return array($elementSetName, $elementName);
    }

    /**
     * Remove duplicate metadata that can be found in all documents.
     */
    protected function _removeDuplicateMetadata()
    {
        $documents = &$this->_processedFiles[$this->_metadataFilepath];

        foreach ($documents as &$document) {
            $document = $this->_removeDuplicateMetadataForRecord($document);
            if (empty($document['files'])) {
                continue;
            }
            foreach ($document['files'] as &$file) {
                $file = $this->_removeDuplicateMetadataForRecord($file);
            }
        }
    }

    /**
     * Remove duplicate metadata of a single document.
     *
     * @param array $document A document.
     * @return array The cleaned document/
     */
    protected function _removeDuplicateMetadataForRecord($document)
    {
        foreach ($document as $key => &$value) {
            switch ($key) {
                case 'metadata':
                    foreach ($value as &$elements) {
                        // Here, elements are simple strings.
                        $elements = array_map('array_unique', $elements);
                        /*
                        // Omeka element texts are not a simple list of strings.
                        $ets = array();
                        foreach ($elements as &$elementTexts) {
                            $ets = array();
                            foreach ($elementTexts as $i => &$elementText) {
                                // Check if it exists.
                                if (isset($ets[$elementText['text']])) {
                                    unset($elementTexts[$i]);
                                }
                                // This is a unique value..
                                else {
                                    $ets[$elementText['text']] = null;
                                }
                            }
                        }
                        */
                    }
                    break;

                case 'specific':
                    foreach ($value as &$data) {
                        if (is_array($data)) {
                            $data = array_unique($data);
                        }
                    }
                    break;

                case 'extra':
                    foreach ($value as &$data) {
                        // Data may be a simple array of strings or a recursive
                        // array. In all cases, only non-mixed integer keys are
                        // managed.
                        $data = $this->_recursiveArrayNumericUnique($data);
                    }
                    break;
            }
        }
        return $document;
    }


    /**
     * Get unique values in a recursive associative array. Only integer keys may
     * be removed, not string ones. When keys are mixed, all values are kept.
     *
     * @param array $array
     * @return array
     */
    protected function _recursiveArrayNumericUnique($array)
    {
        if (empty($array)) {
            return $array;
        }

        if (!is_array($array)) {
            return $array;
        }

        // Check if keys are mixed: if true, keep them all.
        $countKeys = count($array);
        $countIntegerKeys = count(array_filter(array_keys($array), 'is_integer'));
        if ($countIntegerKeys != $countKeys) {
            return array_map(array($this, '_recursiveArrayNumericUnique') , $array);
        }

        // Array _unique works only on scalar values.
        $subArrays = array_filter($array, 'is_array');
        $countSubArrays = count($subArrays);
        if ($countSubArrays == 0) {
            return array_unique($array);
        }

        if ($countSubArrays != $countKeys) {
            $subScalars = array_unique(array_diff_key($array, $subArrays));
            $subArrays = array_map(array($this, '_recursiveArrayNumericUnique'), $subArrays);
            return array_merge($subScalars, $subArrays);
        }

        return array_map(array($this, '_recursiveArrayNumericUnique') , $array);
    }

    /**
     * Unzip a file to get the selected file content.
     *
     * @param string $zipFile
     * @param string $filename The path to extract from the zip file.
     * @return string|null The content of the requested file. Null if error.
     */
    protected function _extractZippedContent($zipFile, $filename)
    {
        $content = null;
        if (class_exists('ZipArchive')) {
            // First, save the file in the temp directory, because ZipArchive
            // doesn't manage url.
            $file = tempnam(sys_get_temp_dir(), basename($zipFile));
            $result = file_put_contents($file, file_get_contents($zipFile));
            if (!empty($result)) {
                $zip = new ZipArchive;
                if ($zip->open($file) === true) {
                    $index = $zip->locateName($filename);
                    if ($index !== false) {
                        $content = $zip->getFromIndex($index);
                    }
                    $zip->close();
                }
            }
        }
        return $content;
    }

    /**
     * Apply a process (xslt stylesheet) on an input (xml file) and save output.
     *
     * @param string $input Path of input file.
     * @param string $stylesheet Path of the stylesheet.
     * @param string $output Path of the output file. If none, a temp file will
     * be used.
     * @param array $parameters Parameters array.
     * @return string|null Path to the output file if ok, null else.
     */
    protected function _processXslt($input, $stylesheet, $output = '', $parameters = array())
    {
        return $this->_processXslt->processXslt($input, $stylesheet, $output, $parameters);
    }

    /**
     * Set the xml format of all documents, specially if a sub class is used.
     */
    protected function _setXmlFormat()
    {
        $documents = &$this->_processedFiles[$this->_metadataFilepath];

        foreach ($documents as &$document) {
            if (isset($document['process']['xml'])) {
                $document['process']['format_xml'] = $this->_formatXml;
            }
            else {
                unset($document['process']['format_xml']);
            }
            if (isset($document['files'])) {
                foreach ($document['files'] as &$file) {
                    if (isset($file['process']['xml'])) {
                        $file['process']['format_xml'] = $this->_formatXml;
                    }
                    else {
                        unset($file['process']['format_xml']);
                    }
                }
            }
        }
    }

    /**
     * Check if a string is an Xml one.
     *
     * @param string $string
     * @return boolean
     */
    protected function _isXml($string)
    {
        $string = trim($string);
        return !empty($string)
            && strpos($string, '<') !== false
            && strpos($string, '>') !== false
            // A main tag is added to allow inner ones.
            && (boolean) simplexml_load_string('<xml>' . $string . '</xml>', 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING);
    }

    /**
     * Convert a string into a list of key / values.
     *
     * @internal The input is already checked via Zend form validator.
     *
     * @param array|string $input
     * @return array
     */
    protected function _stringParametersToArray($input)
    {
        if (is_array($input)) {
            return $input;
        }

        $parameters = array();

        $parametersAdded = array_values(array_filter(array_map('trim', explode(PHP_EOL, $input))));
        foreach ($parametersAdded as $parameterAdded) {
            list($paramName, $paramValue) = explode('=', $parameterAdded);
            $parameters[trim($paramName)] = trim($paramValue);
        }

        return $parameters;
    }
}
