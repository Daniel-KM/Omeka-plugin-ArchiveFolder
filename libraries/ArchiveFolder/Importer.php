<?php

/**
 * ArchiveFolder_Importer class
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Importer
{
    // Default values.
    const IDFIELD_NONE = 'none';
    const IDFIELD_INTERNAL_ID = 'internal id';
    const IDFIELD_NAME = 'name';

    const DEFAULT_IDFIELD = 'none';

    const ACTION_UPDATE_ELSE_CREATE = 'update else create';
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_ADD = 'add';
    const ACTION_REPLACE = 'replace';
    const ACTION_DELETE = 'delete';
    const ACTION_SKIP = 'skip';

    const DEFAULT_ACTION = 'update else create';

    // Options for the process.
    protected $_folder;
    protected $_identifierField;

    protected $_transferStrategy;

    // Remember the first level record when a sub-record is imported.
    protected $_firstLevelRecord;
    protected $_indexFirstLevelRecord = 0;

    // The mapper of a xml document into an internal document.
    protected $_mapper;

    /**
     * Process import of a folder.
     *
     * @internal Pre-checks are done by Archive Folder.
     *
     * @param ArchiveFolder_Folder $folder The folder to process.
     * @return boolean Success or not. An error may be thrown.
     */
    public function process(ArchiveFolder_Folder $folder)
    {
        $this->_folder = $folder;
        $this->_mapper = new ArchiveFolder_Mapping_Document($folder->uri, $folder->getParameters());

        $index = $folder->countImportedRecords() + 1;
        $recordXml = $this->_getXmlRecord($index);
        if (!is_object($recordXml)) {
            $message = __('There is no record #%d.', $index);
            throw new ArchiveFolder_ImporterException($message);
        }

        // Check if this is an empty record.
        if (empty($recordXml)) {
            // Do a special check with Dublin Core values.
            $recordXml->registerXPathNamespace(ArchiveFolder_Format_Document::DC_PREFIX, ArchiveFolder_Format_Document::DC_NAMESPACE);
            $recordXml->registerXPathNamespace(ArchiveFolder_Format_Document::DCTERMS_PREFIX, ArchiveFolder_Format_Document::DCTERMS_NAMESPACE);
            $xpath = 'dc:*|dcterms:*';
            $dcs = $recordXml->xpath($xpath);
            if (empty($dcs)) {
                $message = __('The record #%d is empty.', $index);
                $this->_folder->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_NOTICE);
                return true;
            }
        }

        // Set the default identifier field, that may be bypassed by a record.
        $this->_identifierField = $this->_folder->getParameter('identifier_field') ?: ArchiveFolder_Importer::DEFAULT_IDFIELD;

        // Get the document from the xml file.
        $document = $this->_mapper->getDocument($recordXml, false);

        // Check and add default values to simplify next steps.
        $document = $this->_normalizeDocument($document, $index, $recordXml);

        if ($document['process']['action'] == ArchiveFolder_Importer::ACTION_SKIP) {
            return true;
        }

        // Get the existing Omeka record if any.
        $record = $this->_getExistingRecordFromDocument($document);

        // When there is no identifier or record, the only available action is
        // "Create", else all actions are available.
        if (empty($record)) {
            if ($document['process']['action'] == ArchiveFolder_Importer::ACTION_DELETE) {
                $message = __("The record #%d doesn't exist.", $document['process']['index'])
                    . ' ' . __('The action "%s" has no effect.', $document['process']['action']);
                $this->_folder->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_NOTICE);
                return true;
            }
            if (in_array($document['process']['action'], array(
                    ArchiveFolder_Importer::ACTION_UPDATE,
                    ArchiveFolder_Importer::ACTION_ADD,
                    ArchiveFolder_Importer::ACTION_REPLACE,
                ))) {
                $message = __("The record #%d doesn't exist.", $document['process']['index'])
                    . ' ' . __('The action "%s" is replaced by a creation.', $document['process']['action']);
                $this->_folder->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_NOTICE);
            }
            $document['process']['action'] = ArchiveFolder_Importer::ACTION_CREATE;
        }
        // When there is a record, it can't be re-created.
        else {
            if (in_array($document['process']['action'], array(
                    ArchiveFolder_Importer::ACTION_CREATE,
                ))) {
                $message = __('Cannot process action "%s" for record #%d: the record exists (%s id %d) ).',
                    $document['process']['action'], $document['process']['index'], get_class($record), $record->id);
                throw new ArchiveFolder_ImporterException($message);
            }
        }

        // TODO Add an option to check or not the existing elements and to warn.
        // Move unexisting elements into extra data.
        $document = $this->_checkExistingMetadataElements($document);

        // Process the action.
        switch ($document['process']['action']) {
            case ArchiveFolder_Importer::ACTION_CREATE:
                // Check if a duplicate is to be created (here, there is a record).
                // A same identifier is possible only for different record
                // (internal id) and even in that case, this is not recommended.
                if (!empty($record)
                        && !empty($document['process']['identifier'])
                        && ($document['process']['identifier field'] != ArchiveFolder_Importer::IDFIELD_INTERNAL_ID
                            || get_class($record) == $document['process']['record type'])
                    ) {
                    $message = __('Cannot create a second record with the same identifier (record #%d).',
                        $document['process']['index']);
                    throw new ArchiveFolder_ImporterException($message);
                }
                $record = $this->_createRecord($document);
                break;
            case ArchiveFolder_Importer::ACTION_UPDATE_ELSE_CREATE:
                // Simplify action because the record exist (checked above).
                $document['process']['action'] = ArchiveFolder_Importer::ACTION_UPDATE;
            case ArchiveFolder_Importer::ACTION_UPDATE:
            case ArchiveFolder_Importer::ACTION_ADD:
            case ArchiveFolder_Importer::ACTION_REPLACE:
                $record = $this->_updateRecord($record, $document);
                break;
            case ArchiveFolder_Importer::ACTION_DELETE:
                $record = $this->_deleteRecord($record, $document);
                break;
            default:
                $message = __('Action "%s" does not exist.', $document['process']['action']);
                throw new ArchiveFolder_ImporterException($action);
        }

        return (boolean) $record;
    }

    /**
     * Insert a new record into the Omeka database from a document.
     *
     * @param array $document A normalized document.
     * @return Record|null
     */
    protected function _createRecord($document)
    {
        // Keep only non empty fields.
        $document['metadata'] = $this->_removeEmptyElements($document['metadata']);

        $record = null;
        switch ($document['process']['record type']) {
            case 'File':
                $record = $this->_createFile($document);
                break;
            case 'Item':
                $record = $this->_createItem($document);
                break;
            case 'Collection':
                $record = $this->_createCollection($document);
                break;
        }

        return $record;
    }

    /**
     * Create a collection from a document.
     *
     * @param array $document A normalized document.
     * @return Collection|null The inserted collection or null.
     */
    protected function _createCollection($document)
    {
        $record = $this->_insertCollection($document['specific'], $document['metadata'], $document['extra']);
        $this->_archiveRecord($record, $document['process']['index']);
        return $record;
    }

    /**
     * Create an item from a document.
     *
     * @param array $document A normalized document.
     * @return Item|null The inserted item or null.
     */
    protected function _createItem($document)
    {
        // Check and create collection if needed.
        // TODO Add an option to create or not a default collection.
        $collection = null;
        if (!empty($document['specific']['collection'])) {
            $collection = $this->_createCollectionFromIdentifier($document['specific']['collection']);
            $document['specific'][Builder_Item::COLLECTION_ID] = $collection->id;
            unset($document['specific']['collection']);
        }

        $record = $this->_insertItem($document['specific'], $document['metadata'], array(), $document['extra']);
        $this->_archiveRecord($record, $document['process']['index']);
        return $record;
    }

    /**
     * Attach a new file and its metadata to an item.
     *
     * @param array $document A normalized document.
     * @return File|null The inserted file or null.
     */
    protected function _createFile($document)
    {
        // Check if the file url is present.
        if (empty($document['process']['fullpath'])) {
            return;
        }

        // Get record from the main record, that is saved before.
        // TODO Get the item via the specific "item" name.
        $item = $this->_folder->getRecord($this->_indexFirstLevelRecord);
        if (empty($item)) {
            $message = __('The file "%s" cannot be created before the item.', $document['specific']['path']);
            throw new ArchiveFolder_ImporterException($message);
        }

        $fileUrl = $document['process']['fullpath'];

        // Set the transfer strategy according to the file url.
        $parsedFileUrl = parse_url($fileUrl);
        if (!isset($parsedFileUrl['scheme']) || $parsedFileUrl['scheme'] == 'file') {
            $transferStrategy = 'Filesystem';
            $fileUrlOriginal = $fileUrl;
            $fileUrl = $parsedFileUrl['path'];
            if (!$this->_allowLocalPath($fileUrl)) {
                $message = __('Local paths are not allowed by the administrator (%s) [%s].',
                    $fileUrlOriginal, !isset($parsedFileUrl['scheme']) ? 'no scheme' : 'file scheme');
                throw new ArchiveFolder_ImporterException($message);
            }
        }
        // Url is the recommended scheme.
        else {
            $transferStrategy = 'Url';
            $fileUrl = $this->_rawUrlEncode($fileUrl);
        }

        // Import the file and attach it to the item.
        try {
            $files = insert_files_for_item($item,
                $transferStrategy,
                $fileUrl,
                array('ignore_invalid_files' => false));
        } catch (Omeka_File_Ingest_InvalidException $e) {
            $message = __('Error occurred when attempting to ingest "%s" as a file: %s', $fileUrl, $e->getMessage());
            throw new ArchiveFolder_ImporterException($message);
        }
        // Need to release file in order to update all current data, because
        // $file->save() is not enough.
        $fileId = $files[0]->id;
        release_object($files);
        $record = get_db()->getTable('File')->find($fileId);

        // Update the file with metadata.
        $document['process']['action'] = ArchiveFolder_Importer::ACTION_UPDATE;
        $record = $this->_updateRecord($record, $document);
        return $record;
    }

    /**
     * Check and create a collection if needed.
     *
     * @param array $identifier
     * @return Collection|null The collection if success, else null.
     */
    protected function _createCollectionFromIdentifier($identifier)
    {
        if (empty($identifier)) {
            return;
        }

        // Check the collection before creation.
        $collection = $this->_getExistingRecordFromIdentifier($identifier, 'Collection', $this->_identifierField);
        if (!$collection) {
            $document = array();

            $document['process'] = array();
            $document['process']['index'] = 0;
            $document['process']['record type'] = 'Collection';
            $document['process']['action'] = ArchiveFolder_Importer::ACTION_CREATE;
            $document['process']['identifier field'] = ArchiveFolder_Importer::IDFIELD_NAME;
            $document['process']['identifier'] = $identifier;

            $document['specific'] = array();
            $document['specific'][Builder_Collection::IS_PUBLIC] = false;
            $document['specific'][Builder_Collection::IS_FEATURED] = false;

            $document['metadata'] = array();
            $document['metadata']['Dublin Core']['Title'][] = array('text' => $identifier, 'html' => false);
            $document['metadata']['Dublin Core']['Identifier'][] = array('text' => $identifier, 'html' => false);
            $document['metadata']['Dublin Core']['Source'][] = array('text' => $this->_folder->uri, 'html' => false);

            $document['extra'] = array();

            $collection = $this->_createCollection($document);
        }

        return $collection;
    }

    /**
     * Update metadata and extra data to an existing record.
     *
     * @param Record $record An existing and checked record object.
     * @param array $document A normalized document.
     * @return Record|boolean The updated record or false if error.
     */
    protected function _updateRecord($record, $document)
    {
        // Check action.
        $action = $document['process']['action'];
        if (!in_array($action, array(
                ArchiveFolder_Importer::ACTION_UPDATE,
                ArchiveFolder_Importer::ACTION_ADD,
                ArchiveFolder_Importer::ACTION_REPLACE,
            ))) {
            $message = __('Only update actions are allowed here, not "%s".', $action);
            throw new ArchiveFolder_ImporterException($message);
        }

        $recordType = get_class($record);
        if ($document['process']['record type'] != $recordType) {
            $message = __('The record type "%s" is not the same than the record to update ("%s").',
                $document['process']['record type'], $recordType);
            throw new ArchiveFolder_ImporterException($message);
        }

        // The Builder doesn't allow action "Update", only "Add" and "Replace",
        // and it doesn't manage file directly.

        // Prepare element texts.
        $elementTexts = $document['metadata'];
        // Trim metadata to avoid spaces.
        $elementTexts = $this->_trimElementTexts($elementTexts);
        // Keep only the non empty metadata to avoid removing them with Omeka
        // methods, and to allow update.
        if ($action == ArchiveFolder_Importer::ACTION_ADD || $action == ArchiveFolder_Importer::ACTION_REPLACE) {
            $elementTexts = $this->_removeEmptyElements($elementTexts);
        }
        // Overwrite existing element text values if wanted.
        if ($action == ArchiveFolder_Importer::ACTION_UPDATE || $action == ArchiveFolder_Importer::ACTION_REPLACE) {
            $elementsToDelete = array();
            foreach ($elementTexts as $elementSetName => $elements) {
                foreach ($elements as $elementName => $elementTexts) {
                    $element = $this->_getElementFromIdentifierField($elementSetName . ':' . $elementName);
                    if ($element) {
                        $elementsToDelete[] = $element->id;
                    }
                }
            }
            if ($elementsToDelete) {
                $record->deleteElementTextsbyElementId($elementsToDelete);
            }
        }

        // Update the specific metadata and the element texts.
        // Update is different for each record type.
        switch ($recordType) {
            case 'Item':
                // Check and create collection if needed.
                // TODO Add an option to create or not a default collection.
                $collection = null;
                if (!empty($document['specific']['collection'])) {
                    $collection = $this->_createCollectionFromIdentifier($document['specific']['collection']);
                    $document['specific'][Builder_Item::COLLECTION_ID] = $collection->id;
                    unset($document['specific']['collection']);
                }

                // Update specific data of the item.
                switch ($action) {
                    case ArchiveFolder_Importer::ACTION_UPDATE:
                        // The item type is cleaned: only the name is available,
                        // if any.
                        if (empty($document['specific'][Builder_Item::ITEM_TYPE_NAME])) {
                            // TODO Currently, item type cannot be reset.
                            // $recordMetadata[Builder_Item::ITEM_TYPE_NAME] = null;
                            unset($document['specific'][Builder_Item::ITEM_TYPE_NAME]);
                        }
                        break;

                    case ArchiveFolder_Importer::ACTION_ADD:
                    case  ArchiveFolder_Importer::ACTION_REPLACE:
                        if (empty($document['specific'][Builder_Item::COLLECTION_ID])) {
                            $document['specific'][Builder_Item::COLLECTION_ID] = $record->collection_id;
                        }
                        if (empty($document['specific'][Builder_Item::ITEM_TYPE_NAME])) {
                            if (!empty($record->item_type_id)) {
                                $document['specific'][Builder_Item::ITEM_TYPE_ID] = $record->item_type_id;
                            }
                            unset($document['specific'][Builder_Item::ITEM_TYPE_NAME]);
                        }
                        break;
                }

                if (empty($document['specific'][Builder_Item::TAGS])) {
                    unset($document['specific'][Builder_Item::TAGS]);
                }

                $record = update_item($record, $document['specific'], $document['metadata']);
                break;

            case 'File':
                $record->addElementTextsByArray($document['metadata']);
                $record->save();
                break;

            case 'Collection':
                $record = update_collection($record, $document['specific'], $document['metadata']);
                break;

            default:
                $message = __('Record type "%s" is not allowed.', $recordType);
                throw new ArchiveFolder_ImporterException($message);
        }

        // Update the extra metadata.
        $this->_setExtraData($record, $document['extra'], $action);

        $this->_archiveRecord($record, $document['process']['index']);
         return $record;
    }

    /**
     * Delete an existing record.
     *
     * @param Record $record The existing and checked record object to remove.
     * @param array $document A normalized document.
     * @return boolean Success.
     */
    protected function _deleteRecord($record, $document)
    {
        if ($record instanceof Omeka_Record_AbstractRecord) {
            // Deletion of a record return a boolean.
            try {
                $record = $record->delete();
            } catch (Omeka_Record_Exception $e) {
                $message = __('Unable to delete the record: %s', $e->getMessage());
                throw new ArchiveFolder_ImporterException($message);
            } catch (Exception $e) {
                $message = __('Unable to delete this record: %s', $e->getMessage());
                throw new ArchiveFolder_ImporterException($message);
            }
            $this->_archiveRecord($record, $document['process']['index']);
            return true;
        }
        return false;
    }

    /**
     * Get the xml content of a record for the specified index.
     *
     * If the record is a sub-record, the first level record is saved.
     *
     * @param string $index
     * @return SimpleXml|null The record if found, null if not found.
     */
    protected function _getXmlRecord($index)
    {
        if (empty($index)) {
            return false;
        }

        $reader = $this->_getXmlReader();
        if (!$reader) {
            return false;
        }

        $recordXml = null;
        $total = 0;
        $firstLevel = true;
        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT
                    && $reader->name == 'record'
                ) {
                $total++;

                // Check if the current record is a first level one in order to
                // keep it for next process.
                $currentRecord = $reader->readOuterXml();
                $currentRecordXml = @simplexml_load_string($currentRecord, 'SimpleXMLElement');

                if ($firstLevel) {
                    $this->_firstLevelRecord = $currentRecordXml;
                    $this->_indexFirstLevelRecord = $total;
                }

                if ($total == $index) {
                    $recordXml = $currentRecordXml;
                    break;
                }

                // Skip sub records to increase the speed of the process.
                $totalSubRecords = count($currentRecordXml->record);
                if ($total + $totalSubRecords < $index) {
                    $reader->next();
                    $total += $totalSubRecords;
                }
                // The wanted record is a sub one.
                else {
                    $firstLevel = false;
                }
            }
        }

        $reader->close();

        return $recordXml;
    }

    protected function _getXmlReader()
    {
        // Prepare the xml reader for the existing static repository.
        // Don't use a static value to allow tests.
        $localRepositoryFilepath = $this->_folder->getLocalRepositoryFilepath();
        if (!file_exists($localRepositoryFilepath)) {
            $message = __('The repository file "%s" does not exist.', $localRepositoryFilepath);
            throw new ArchiveFolder_ImporterException($message);
        }

        // Read the xml from the beginning.
        $reader = new XMLReader;
        $result = $reader->open($localRepositoryFilepath, null, LIBXML_NSCLEAN);
        if (!$result) {
            $message = __('The repository file "%s" is not an xml one.', $localRepositoryFilepath);
            throw new ArchiveFolder_ImporterException($message);
        }

        return $reader;
    }

    /**
     * Normalize the document and set default values according to parameters.
     *
     * @param array $document A cleaned document.
     * @param integer $index
     * @param SimpleXml $recordXml
     * @return array $document
     */
    private function _normalizeDocument($document, $index, $recordXml)
    {
        $document['process']['index'] = $index;

        // Set the default action.
        if (empty($document['process']['action'])) {
            $document['process']['action'] = $this->_folder->getParameter('action') ?: ArchiveFolder_Importer::DEFAULT_ACTION;
        }

        // Set the default identifier field.
        $document['process']['identifier field'] = empty($document['process']['identifier field'])
            ? $this->_identifierField
            : $document['process']['identifier field'];

        // Get the identifier if any.
        $identifierField = $document['process']['identifier field'];
        if (empty($identifierField) || $identifierField == ArchiveFolder_Importer::IDFIELD_NONE) {
            $document['process']['identifier'] = '';
        }

        // Default case is the internal id.
        elseif ($identifierField == ArchiveFolder_Importer::IDFIELD_INTERNAL_ID) {
            $document['process']['identifier'] = empty($document['process']['internal id']) ? 0 : (integer) $document['process']['internal id'];
        }

        // Name may be the Identifier.
        elseif ($identifierField == ArchiveFolder_Importer::IDFIELD_NAME) {
            $document['process']['identifier'] = empty($document['process']['name']) ? '' : $document['process']['name'];
        }

        // Other cases.
        else {
            // Manage specific fields for file.
            $fieldFile = false;
            if ($document['process']['record type'] == 'File' && in_array($identifierField, array(
                    'original filename',
                    'filename',
                    'authentication',
                ))) {
                $document['process']['identifier'] = empty($document['specific'][$identifierField]) ? '' : $document['specific'][$identifierField];
            }

            // Get the record with a standard field.
            if (empty($fieldFile)) {
                $element = $this->_getElementFromIdentifierField($document['process']['identifier field']);
                if ($element) {
                    $elementSetName = $element->getElementSet()->name;
                    if (empty($document['metadata'][$elementSetName][$element->name])) {
                        $document['process']['identifier'] = null;
                    }
                    // Here, the value is still a list of simple strings.
                    else {
                        $document['process']['identifier'] = $document['metadata'][$elementSetName][$element->name];
                    }
                }
                // The identifier doesn't exist.
                else {
                    $document['process']['identifier'] = null;
                }
            }
        }

        // Normalize the element texts.
        // Add the html boolean to be Omeka compatible.
        foreach ($document['metadata'] as $elementSetName => &$elements) {
            foreach ($elements as $elementName => &$elementTexts) {
                foreach ($elementTexts as &$elementText) {
                    // Trim the metadata too to avoid useless spaces.
                    if (is_array($elementText)) {
                        $elementText['text'] = trim($elementText['text']);
                        $elementText['html'] = !empty($elementText['html']);
                    }
                    // Normalize the value.
                    else {
                        $elementText = trim($elementText);
                        $elementText = array(
                            'text' => $elementText,
                            'html' => $this->_isXml($elementText),
                        );
                    }
                }
            }
        }

        return $document;
    }

    /**
     * Check if metadata are existing elements and move other into extra data.
     *
     * This avoids an error during import, because Omeka checks it too.
     *
     * @internal Elements depend on the record type.
     * @internal Move it earlier during checking / normalization?
     * @todo Warn if some elements move to extra (this may be an error).
     *
     * @param array $document A normalized document.
     * @param array The checked document.
     */
    protected function _checkExistingMetadataElements($document)
    {
        $db = get_db();

        // Prepare the list of names of element sets.
        // Function findPairsForSelectForm() is not used because it's filtered.
        $elementSets = empty($document['process']['record type'])
            ? $db->getTable('ElementSet')->findAll()
            : $db->getTable('ElementSet')->findByRecordType($document['process']['record type'], true);
        $elementSetNames = array();
        foreach ($elementSets as $elementSet) {
            $elementSetNames[$elementSet->name] = null;
        }
        // Add the non-existing element sets to extra.
        $extraMetadata = array_diff_key($document['metadata'], $elementSetNames);
        $document['metadata'] = array_intersect_key($document['metadata'], $elementSetNames);

        // Check the remaining elements in each element set.
        foreach ($document['metadata'] as $elementSetName => $elements) {
            // Prepare the list of names of elements.
            $elementsObjects = $db->getTable('Element')->findBySet($elementSetName);
            $elementNames = array();
            foreach ($elementsObjects as $elementObject) {
                $elementNames[$elementObject->name] = null;
            }
            $diff = array_diff_key($elements, $elementNames);
            if ($diff) {
                $extraMetadata[$elementSetName] = $diff;
                $document['metadata'][$elementSetName] = array_intersect_key($elements, $elementNames);
            }
        }

        // Normalize new extra metadata. Values will be arrays, because elements
        // are repeatable. To get single values, don't use the delimiter ":"
        // like "geolocation:latitude", but the "[]" like "geolocation[latitude]".
        // With the advanced geolocation plugin that allows multiple points by
        // item, use "geolocation[][latitude]". Or use "extra" directly.
        if ($extraMetadata) {
            foreach ($extraMetadata as &$elements) {
                foreach ($elements as &$elementTexts) {
                    foreach ($elementTexts as &$elementText) {
                        $elementText = $elementText['text'];
                    }
                }
                $document['extra'] = array_merge_recursive($document['extra'], $extraMetadata);
            }
        }

        return $document;
    }

    /**
     * Get existing record from a document, if any.
     *
     * @param array $document A normalized document.
     * @return Record|null The record if any.
     */
    protected function _getExistingRecordFromDocument($document)
    {
        return $this->_getExistingRecordFromIdentifier(
            $document['process']['identifier'],
            $document['process']['record type'],
            $document['process']['identifier field']);
    }

    /**
     * Get existing record from an identifier, if any.
     *
     * @param string $identifier The identifier of the record to update.
     * @param string $recordType The type of the record to update.
     * @param string $identifierField The type of identifier used to identify
     * @return Record|null The record if any.
     */
    protected function _getExistingRecordFromIdentifier(
        $identifier,
        $recordType = 'Item',
        $identifierField = ArchiveFolder_Importer::DEFAULT_IDFIELD
    ) {
        if (empty($identifier)
                || empty($identifierField)
                || $identifierField == ArchiveFolder_Importer::IDFIELD_NONE
            ) {
            return;
        }

        $db = get_db();
        $record = null;

        if ($identifierField == ArchiveFolder_Importer::IDFIELD_INTERNAL_ID) {
            $record = $db->getTable($recordType)->find($identifier);
            return $record;
        }

        // Manage specific fields for file.
        if ($recordType == 'File' && in_array($identifierField, array(
                'original filename',
                'filename',
                'authentication',
            ))) {
            if ($identifierField == 'original filename') {
                $identifierField = 'original_filename';
            }
            $record = $db->getTable('File')->findBySql($identifierField . ' = ?', array($identifier), true);
            return $record;
        }

        // Get the record with a standard field. The element is already checked.
        $element = $this->_getElementFromIdentifierField($identifierField);
        $elementSetName = $element->getElementSet()->name;

        // Use of ordered placeholders.
        $bind = array();
        $bind[] = $element->id;

        $identifiers = is_array($identifier) ? $identifier :  array($identifier);
        if (count($identifiers) == 1) {
            $sqlElementText = 'AND element_texts.text = ?';
            $bind[] = reset($identifiers);
        }
        // Search in a list of identifiers.
        else {
            $quoted = $db->quote($identifiers);
            $sqlElementText = "AND element_texts.text IN ($quoted)";
        }

        if (empty($recordType)) {
            $sqlRecordType = '';
        }
        else {
            $sqlRecordType = 'AND element_texts.record_type = ?';
            $bind[] = $recordType;
        }

        $sql = "
            SELECT element_texts.record_type, element_texts.record_id
            FROM {$db->ElementText} element_texts
            WHERE element_texts.element_id = ?
                $sqlElementText
                $sqlRecordType
            LIMIT 1
        ";
        $result = $db->fetchRow($sql, $bind);
        if ($result) {
            $record = $db->getTable($result['record_type'])->find($result['record_id']);
            return $record;
        }
    }

    /**
     * Return the element from an identifier.
     *
     * @param integer|string $identifierField
     * @return Element|null
     */
    private function _getElementFromIdentifierField($identifierField)
    {
        static $elements = array();

        if (empty($identifierField)) {
            return;
        }

        if (!isset($elements[$identifierField])) {
            $element = null;
            if (is_numeric($identifierField)) {
                $element = get_db()->getTable('Element')->find($identifierField);
            }
            // This is a string.
            else {
                $parts = explode(':', $identifierField);
                if (count($parts) == 2) {
                    $elementSetName = trim($parts[0]);
                    $elementName = trim($parts[1]);
                    $element = get_db()->getTable('Element')
                        ->findByElementSetNameAndElementName($elementSetName, $elementName);
                }
            }
            $elements[$identifierField] = $element;
        }

        return $elements[$identifierField];
    }

    /**
     * Keep only non empty fields in the metadata of a document
     *
     * @param array $metadata An array of normalized metadata.
     * @return array A filtered array of metadata.
     */
    private function _removeEmptyElements($metadata)
    {
        foreach ($metadata as $elementSetName => &$elements) {
            foreach ($elements as $elementName => &$elementTexts) {
                $elementTexts = array_values(array_filter($elementTexts, 'self::_removeEmptyElement'));
            }
        }
        return $metadata;
    }

    /**
     * Check if an element is an element without empty string .
     *
     * @param string $element Element to check.
     * @return boolean True if the element is an element without empty string.
     */
    private function _removeEmptyElement($element)
    {
        // Don't remove 0.
        return isset($element['text']) && $element['text'] !== '';
    }

    /**
     * Check if an element is an element without empty string.
     *
     * @param string $element Element to check.
     * @return array Array of trimed element texts.
     */
    private function _trimElementTexts($elementTexts)
    {
        foreach ($elementTexts as &$element) {
            if (isset($element['text'])) {
                $element['text'] = trim($element['text']);
            }
        }
        return $elementTexts;
    }

    /**
     * Raw url encode a full url if needed.
     *
     * @param string $fileUrl
     * @return string The file url.
     */
    private function _rawUrlEncode($url)
    {
        if (rawurldecode($url) == $url) {
            $path = substr($url, strpos($url, '/'));
            $url = substr($url, 0, strpos($url, '/'))
                . implode('/', array_map('rawurlencode', explode('/', $path)));
        }
        return $url;
    }


    /**
     * Check if a local file is importable.
     *
     * @param $fileUrl
     * @return boolean
     */
    private function _allowLocalPath($fileUrl)
    {
        $settings = Zend_Registry::get('archive_folder');

        // Check the security setting.
        if ($settings->local_folders->allow != '1') {
            return false;
        }

        // Check the base path.
        $path = $settings->local_folders->base_path;
        $realpath = realpath($path);
        if (rtrim($path, '/') !== $realpath || strlen($realpath) <= 2) {
            return false;
        }

        // Check the uri.
        if ($settings->local_folders->check_realpath == '1') {
            if (strpos(realpath($fileUrl), $realpath) !== 0
                    || !in_array(substr($fileUrl, strlen($realpath), 1), array('', '/'))
                ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a string is an Xml one.
     *
     * @param string $string
     * @return boolean
     */
    private function _isXml($string)
    {
        $string = trim($string);
        return !empty($string)
            && strpos($string, '<') !== false
            && strpos($string, '>') !== false
            // A main tag is added to allow inner ones.
            && (boolean) simplexml_load_string('<xml>' . $string . '</xml>', 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING);
    }

    /**
     * Record the process once done, if it has not been recorded yet.
     *
     * All processes (create, update or delete) are recorded.
     *
     * @param Record $record
     * @param integer $index The index in the folder, or 0.
     * @return boolean|null Success or not.
     */
    protected function _archiveRecord($record, $index = 0)
    {
        // Check if the archive record for this record exists.
        $archiveRecords = get_db()->getTable('ArchiveFolder_Record')
            ->findByFolderAndRecord($this->_folder, $record);
        if ($archiveRecords) {
            // Set the index if possible, for example when a record is created
            // before its metadata.
            if ($index) {
                $archiveRecord = reset($archiveRecords);
                if ($archiveRecord->index == 0) {
                    $archiveRecord->setIndex($index);
                    $archiveRecord->save();
                }
            }
            return true;
        }

        $archiveRecord = new ArchiveFolder_Record();
        $archiveRecord->setFolder($this->_folder);
        $archiveRecord->setIndex($index);
        $archiveRecord->setRecord($record);
        $result = $archiveRecord->save();
        if (!$result) {
            $message = __('Cannot archive the record %s #%d [index #%d].',
                get_class($record), $record->id, $index);
            throw new ArchiveFolder_ImporterException($message);
        }

        return true;
    }

    /* Functions that override Omeka ones in order to process extra data. */

    /**
     * Insert a new collection into the Omeka database.
     *
     * Post data can be added, unlike insert_collection().
     *
     * @see insert_collection()
     *
     * @param array $metadata
     * @param array $elementTexts
     * @param array $postData
     * @return Item
     */
    private function _insertCollection($metadata = array(), $elementTexts = array(), $postData = array())
    {
        $record = insert_collection($metadata, $elementTexts);
        $result = $this->_setExtraData($record, $postData, ArchiveFolder_Importer::ACTION_ADD);
        return $record;
    }

    /**
     * Insert a new item into the Omeka database.
     *
     * Post data can be added, unlike insert_item().
     *
     * @see insert_item()
     *
     * @param array $metadata
     * @param array $elementTexts
     * @param array $fileMetadata
     * @param array $postData
     * @return Item
     */
    private function _insertItem($metadata = array(), $elementTexts = array(), $fileMetadata = array(), $postData = array())
    {
        $record = insert_item($metadata, $elementTexts, $fileMetadata);
        $result = $this->_setExtraData($record, $postData, ArchiveFolder_Importer::ACTION_ADD);
        return $record;
    }

    /**
     * Helper to set extra data for update of records.
     *
     * @internal $action is currently not used, because the way plugins manage
     * updates of their data varies.
     *
     * @todo Manage action via delete/add data?
     *
     * @param Record $record
     * @param array $extraData
     * @param string $action Currently not used.
     * @return boolean Success or not.
     */
    private function _setExtraData(
        $record,
        $extraData,
        $action = ArchiveFolder_Importer::DEFAULT_ACTION
    ) {
        if (empty($record) || empty($extraData) || empty($action)) {
            return false;
        }

        if (Zend_Registry::get('bootstrap')->config->jobs->dispatcher->longRunning
                == 'Omeka_Job_Dispatcher_Adapter_Synchronous') {
            $record->setPostData($extraData);
        }
        // Workaround for asynchronous jobs.
        else {
            $this->_setPostDataViaSetArray($record, $extraData);
        }

        $record->save();
        return true;
    }

    /**
     * Workaround to add post data to a record via setArray().
     *
     * @see CSVImport_Builder_Item::_setPostDataViaSetArray()
     *
     * @param Record $record
     * @param array $post Post data.
     */
    private function _setPostDataViaSetArray($record, $post)
    {
        // Some default type have a special filter.
        switch (get_class($record)) {
            case 'Item':
                $options = array('inputNamespace' => 'Omeka_Filter');
                $filters = array(
                    // Foreign keys
                    Builder_Item::ITEM_TYPE_ID  => 'ForeignKey',
                    Builder_Item::COLLECTION_ID => 'ForeignKey',
                    // Booleans
                    Builder_Item::IS_PUBLIC => 'Boolean',
                    Builder_Item::IS_FEATURED => 'Boolean',
                );
                $filter = new Zend_Filter_Input($filters, null, $post, $options);
                $post = $filter->getUnescaped();
                break;

            case 'File':
                $immutable = array('id', 'modified', 'added', 'authentication', 'filename',
                    'original_filename', 'mime_type', 'type_os', 'item_id');
                foreach ($immutable as $value) {
                    unset($post[$value]);
                }
                break;

            case 'Collection':
                $options = array('inputNamespace' => 'Omeka_Filter');
                // User form input does not allow HTML tags or superfluous whitespace
                $filters = array(
                    Builder_Collection::IS_PUBLIC => 'Boolean',
                    Builder_Collection::IS_FEATURED => 'Boolean',
                );
                $filter = new Zend_Filter_Input($filters, null, $post, $options);
                $post = $filter->getUnescaped();
                break;

            default:
                return;
        }

        // Avoid an issue when the post is null.
        if (empty($post)) {
            return;
        }

        // Avoid an issue when "elements" is not set.
        if (!isset($post['Elements'])) {
            $post['Elements'] = array();
        }

        // Default used in Omeka_Record_Builder_AbstractBuilder::setPostData().
        $post = new ArrayObject($post);
        if (array_key_exists('id', $post)) {
            unset($post['id']);
        }

        $record->setArray(array('_postData' => $post));
    }
}
