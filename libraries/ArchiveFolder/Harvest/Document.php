<?php
/**
 * Metadata format map for the doc Document format.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Harvest_Document extends OaipmhHarvester_Harvest_Abstract
{
    // Xml schema and OAI prefix for the format represented by this class.
    // These constants are required for all maps.
    const METADATA_SCHEMA = 'http://localhost';
    const METADATA_PREFIX = 'doc';

    const XML_PREFIX= self::METADATA_PREFIX;
    const XML_NAMESPACE = 'http://localhost/documents/';
    const DC_PREFIX = 'dc';
    const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    const DC_TERMS_PREFIX = 'dcterms';
    const DC_TERMS_NAMESPACE = 'http://purl.org/dc/terms/';

    // Collection to insert items into.
    protected $_collection;

    // Create collections from records.
    protected $_createCollections = true;

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

    // List of fields that may be attributes of the item (can be in extra too).
    protected $_itemAttributes = array(
        'collection' => 'collection',
        'item type' => 'itemType',
        'featured' => 'featured',
        'public' => 'public',
        // These are  special ones.
        'name' => 'name',
        'action' => 'action',
    );

    /**
     * Class constructor.
     *
     * Prepares the harvest process.
     *
     * @param OaipmhHarvester_Harvest $harvest The OaipmhHarvester_Harvest object
     * model
     * @return void
     */
    public function __construct($harvest)
    {
        if (plugin_is_active('DublinCoreExtended')) {
            $this->_prepareDCQTerms();
        }
        parent::__construct($harvest);
    }

    /**
     * Actions to be carried out before the harvest of any items begins.
     */
    protected function _beforeHarvest()
    {
        $harvest = $this->_getHarvest();

        $collectionMetadata = array(
            'metadata' => array(
                'public' => $this->getOption('public'),
                'featured' => $this->getOption('featured'),
        ));
        $collectionMetadata['elementTexts']['Dublin Core']['Title'][] =
            array('text' => (string) $harvest->set_name, 'html' => false);
        $collectionMetadata['elementTexts']['Dublin Core']['Description'][] =
            array('text' => (string) $harvest->set_Description, 'html' => false);

        $this->_collection = $this->_insertCollection($collectionMetadata);
    }

    /**
     * Harvest one record.
     *
     * @param SimpleXMLIterator $record XML metadata record
     * @return array Array of item-level, element texts and file metadata.
     */
    protected function _harvestRecord($record)
    {
        // Get document record from record.
        $record = $record->metadata->record;
        if (empty($record)) {
            return array(
                'itemMetadata' => array(),
                'elementTexts' => array(),
                'fileMetadata' => array(),
            );
        }

        $current = $this->_getDataForRecord($record);
        $elementTexts = empty($current['metadata']) ? array() : $current['metadata'];
        $extra = empty($current['extra']) ? array() : $current['extra'];
        $fileMetadata = array();

        $itemMetadata = $this->_getItemMetadata($extra);

        // Process files.
        $files = $record->record;
        foreach ($files as $fileXml) {
            $path = trim($this->_getXmlAttribute($fileXml, 'file'));
            // A filepath is needed.
            if (strlen($path) == 0) {
                continue;
            }

            $fileData = $this->_getDataForRecord($fileXml);

            $fileMeta = empty($fileData['metadata']) ? array() : $fileData['metadata'];
            $fileExtra = empty($fileData['extra']) ? array() : $fileData['extra'];
            $fileMetadata['files'][] = array(
                'Upload' => null,
                'Url' => $path,
                'source' => $path,
                //'name'   => (string) $file['title'],
                'metadata' => $fileMeta,
                'extra' => $fileExtra,
            );
        }

        return array(
            'itemMetadata' => $itemMetadata,
            'elementTexts' => $elementTexts,
            'fileMetadata' => $fileMetadata,
            'extra' => $extra,
        );
    }

    /**
     * Ingest specific data and fire the hook "archive_folder_ingest_extra" for
     * the item and each file.
     *
     * @param Record $record
     * @param array $harvestedRecord
     * @param string $performed Can be "inserted", "updated" or "skipped".
     * @return void
     */
    protected function _harvestRecordSpecific($record, $harvestedRecord, $performed)
    {
        // Static repository requires a date without time, so successive updates
        // of the static repository on the same day may not be ingested. This
        // check is a workaround.
        if ($performed == 'skipped' && get_option('archive_folder_force_update')) {
            $record = $this->_updateItem(
                $record,
                $harvestedRecord['elementTexts'],
                $harvestedRecord['fileMetadata']);
        }

        // The record is an item.
        $item = &$record;

        // The file metadata are set in the same order than the files.
        $files = $item->Files;

        // Manage actions for item.
        if (!empty($harvestedRecord['extra']['action'])) {
            $result = $this->_processAction($item, $harvestedRecord);
            if (empty($result)) {
                return;
            }
        }

        // Manage actions for files.
        foreach ($harvestedRecord['fileMetadata'] as $key => $fileMetadata) {
            if (!empty($fileMetadata['extra']['action']) && isset($files[$key])) {
                $result = $this->_processAction($files[$key], $fileMetadata);
                if (empty($result)) {
                    $files[$key] = null;
                }
            }
        }

        // Ingest extra data if any, for item and files.
        if (!empty($harvestedRecord['extra'])) {
            $this->_setExtraData($item, $harvestedRecord['extra']);
        }

        foreach ($harvestedRecord['fileMetadata'] as $key => $fileMetadata) {
            if (!empty($fileMetadata['extra']) && !empty($files[$key])) {
                $this->_setExtraData($files[$key], $fileMetadata['extra']);
            }
        }

        // Call the hook.
        fire_plugin_hook('archive_folder_ingest_data', array(
            'record' => $item,
            'data' => $harvestedRecord,
        ));

        foreach ($harvestedRecord['fileMetadata'] as $key => $fileMetadata) {
            if (!empty($files[$key])) {
                fire_plugin_hook('archive_folder_ingest_data', array(
                    'record' => $files[$key],
                    'data' => $fileMetadata,
                ));
            }
        }
    }

    /**
     * Helper to process a special action on a record.
     *
     * @internal Only deletion is managed: everything else is an update.
     *
     * @param Record $record
     * @param array $data
     * @return boolean|null Success or not, or null for deletion.
     */
    protected function _processAction($record, $data)
    {
        switch (strtolower($data['extra']['action'])) {
            case 'delete':
                $record->delete();
                return null;
        }
    }

    /**
     * Helper to set extra data for update of records.
     *
     * @param Record $record
     * @param array $extraData
     * @return boolean Success or not.
     */
    protected function _setExtraData($record, $extraData)
    {
        if (empty($record) || empty($extraData)) {
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
                    'item_type_id'  => 'ForeignKey',
                    'collection_id' => 'ForeignKey',
                    // Booleans
                    'public' => 'Boolean',
                    'featured' => 'Boolean',
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
                    'public' => 'Boolean',
                    'featured' => 'Boolean',
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

    /**
     * This format can use directly Dublin Core qualified terms.
     */
    protected function _prepareDCQTerms()
    {
        // Prepare labels of dc terms.
        require dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'elements_qdc.php';
        $this->_dcTerms = array();
        foreach ($elements as $element) {
            // Checks are done on lower case names and labels.
            $this->_dcTerms[strtolower($element['name'])] = $element['label'];
            $this->_dcTerms[strtolower($element['label'])] = $element['label'];
        }
    }

    /**
     * Get all data for a record (item or file).
     *
     * @see ArchiveFolder_Mapping_Document::_getDataForRecord()
     *
     * @param SimpleXml $record The item or file, not the oai record.
     * @return array The document array.
     */
    protected function _getDataForRecord($record)
    {
        $current = array();

        $record->registerXPathNamespace('', self::XML_NAMESPACE);
        $record->registerXPathNamespace(self::XML_PREFIX, self::XML_NAMESPACE);

        // Process flat Dublin Core.
        $record->registerXPathNamespace(self::DC_PREFIX, self::DC_NAMESPACE);
        $record->registerXPathNamespace(self::DC_TERMS_PREFIX, self::DC_TERMS_NAMESPACE);
        $xpath = 'dc:*|dcterms:*';
        $dcs = $record->xpath($xpath);
        foreach ($dcs as $dc) {
            $name = strtolower($dc->getName());
            if (isset($this->_dcTerms[$name])) {
                $text = $this->_innerXML($dc);
                $current['metadata']['Dublin Core'][$this->_dcTerms[$name]][] =
                    array('text'=> $text, 'html' => $this->_isXml($text));
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
                    $current['metadata'][$elementSetName][$elementName][] =
                        array('text'=> $text, 'html' => $this->_isXml($text));
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

        // Clean extra data: set single value as string, else let it as array.
        if (isset($current['extra'])) {
            foreach ($current['extra'] as &$value) {
                if (is_array($value)) {
                    if (count($value) == 0) {
                        $value = '';
                    }
                    elseif (count($value) == 1) {
                        $value = reset($value);
                    }
                }
            }
            // Required, because $value is a generic reference used only here.
            unset($value);
        }

        // Normalize extra data names (array notation).
        if (isset($current['extra'])) {
            $extra = array();
            foreach ($current['extra'] as $key => $value) {
                $array = $this->_convertArrayNotation($key);
                $array = $this->_nestArray($array, $value);
                $value = reset($array);
                $name = key($array);
                $extra[] = array($name => $value);
            }
            $extraData = array();
            foreach ($extra as $data) {
                $extraData = array_merge_recursive($extraData, $data);
            }
            $current['extra'] = $extraData;
        }

        return $current;
    }

    /**
     * Get the attribute of an xml element.
     *
     * @see ArchiveFolder_Mapping_Document::_getXmlAttribute()
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
     * Return the full inner content of an xml element, as string or as xml.
     *
     * @todo Fully manage cdata
     *
     * @see ArchiveFolder_Mapping_Document::_innerXML()
     *
     * @param SimpleXml $xml
     * @return string
     */
    protected function _innerXML($xml)
    {
        $innerXml= '';
        foreach (dom_import_simplexml($xml)->childNodes as $child) {
            $innerXml .= $child->ownerDocument->saveXML($child);
        }

        // Only main CDATA is managed, not inside content: if this is an xml or
        // html, it will be managed automatically by the display; if this is a
        // text, the cdata is a text too.
        $simpleXml = simplexml_load_string($innerXml, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
        // Non XML data.
        if (empty($simpleXml)) {
            if ($this->_isCdata($innerXml)) {
                $innerXml = substr($innerXml, 9, strlen($innerXml) - 12);
            }
        }

        return trim($innerXml);
    }

    /**
     * Check if a string is an xml cdata one.
     *
     * @param string $string
     * @return boolean
     */
    protected function _isCdata($string)
    {
        return strpos($string, '<![CDATA[') === 0 && strpos($string, ']]>') === strlen($string) - 3;
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
     * Get special metadata for item.
     *
     * @param array $extra Extra data from the record.
     * @return array
     */
    protected function _getItemMetadata($extra)
    {
        // Set default values.
        $itemMetadata = array(
            'collection_id' => isset($this->_collection->id) ? $this->_collection->id : 0,
            'public' => $this->getOption('public'),
            'featured' => $this->getOption('featured'),
        );

        // Process "collection".
        if (isset($extra['collection'])) {
            $collectionIdentifier = $extra['collection'];
            if (is_numeric($collectionIdentifier) && (integer) $collectionIdentifier > 0) {
                $collection = get_record_by_id('Collection', $collectionIdentifier);
            }
            if (empty($collection)) {
                $collection = $this->_getCollectionByTitle($collectionIdentifier);
            }
            if (empty($collection) && $this->_createCollections) {
                $collection = $this->_createCollectionFromTitle($collectionIdentifier);
            }
            if ($collection) {
                $itemMetadata['collection_id'] = $collection->id;
            }
        }

        // Process "public".
        if (isset($extra['public'])) {
            $itemMetadata['public'] = (boolean) $extra['public'];
        }

        // Process "featured".
        if (isset($extra['featured'])) {
            $itemMetadata['featured'] = (boolean) $extra['featured'];
        }

        // Process "itemType".
        if (isset($extra['item type'])) {
            $itemMetadata['item_type_name'] = $extra['item type'];
        }

        // Process "tags".
        if (isset($extra['Tags'])) {
            $itemMetadata['tags'] = $extra['Tags'];
        }

        return $itemMetadata;
    }

    /**
     * Return a collection by its title.
     *
     * @param string $name The collection name
     * @return Collection The collection
     */
    protected function _getCollectionByTitle($name)
    {
        $db = get_db();

        $elementTable = $db->getTable('Element');
        $element = $elementTable->findByElementSetNameAndElementName('Dublin Core', 'Title');

        $collectionTable = $db->getTable('Collection');
        $select = $collectionTable->getSelect();
        $select->joinInner(array('s' => $db->ElementText),
                           's.record_id = collections.id', array());
        $select->where("s.record_type = 'Collection'");
        $select->where("s.element_id = ?", $element->id);
        $select->where("s.text = ?", $name);

        $collection = $collectionTable->fetchObject($select);
        if (!$collection && !$this->_createCollections) {
            _log('[ArchiveFolder] '. 'Collection not found. Collections must be created with identical names prior to import', Zend_Log::NOTICE);
            return false;
        }
        return $collection;
    }

    /**
     * Create a new collection from a simple raw title.
     *
     * @param string $title
     * @return Collection
     */
    private function _createCollectionFromTitle($title)
    {
        $collection = new Collection;
        $collection->save();
        update_collection($collection, array(), array(
            'Dublin Core' => array(
                'Title' => array(
                    array('text' => $title, 'html' => false),
        ))));
        return $collection;
    }
}
