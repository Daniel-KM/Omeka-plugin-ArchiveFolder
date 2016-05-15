<?php

/**
 * @package ArchiveFolder
 */
class ArchiveFolder_Folder extends Omeka_Record_AbstractRecord implements Zend_Acl_Resource_Interface
{
    /**
     * Error message codes, used for status messages.
     *
     * @see Zend_Log
     */
    const MESSAGE_CODE_ERROR = 3;
    const MESSAGE_CODE_NOTICE = 5;
    const MESSAGE_CODE_INFO = 6;
    const MESSAGE_CODE_DEBUG = 7;

    // Build.
    const STATUS_ADDED = 'added';
    const STATUS_QUEUED = 'queued';
    const STATUS_PROGRESS = 'progress';
    const STATUS_COMPLETED = 'completed';
    // Deletion of the xml document.
    const STATUS_DELETED = 'deleted';
    // Import.
    const STATUS_IMPORTING = 'importing';
    const STATUS_IMPORTED = 'imported';
    // Process.
    const STATUS_PAUSED = 'paused';
    const STATUS_STOPPED = 'stopped';
    const STATUS_KILLED = 'killed';
    const STATUS_RESET = 'reset';
    const STATUS_ERROR = 'error';

    /**
     * @var int The record ID.
     */
    public $id;

    /**
     * @example http://institution.org:8080/path/to/folder
     * @example http://example.org/path/to/folder
     * @example /home/user/path/to/folder
     */
    public $uri;

    /**
     * The identifier part of the repository, used for integrated repositories
     * only. It must be unique. It is used to cache files too, if needed.
     * @example institution_org8080pathtorepository_identifier
     * @example repository_identifier
     */
    public $identifier;

    /**
     * Contains all the parameters of the folder and the resulting urls.
     *
     * "Repository_url": the url to the xml file.
     * "Repository_base_url": the url to the xml file via the gateway.
     * "Repository_short_url": the url to the xml file, without the scheme.
     * - Omeka used as a gateway for a static repository:
     * @example institution.org%3A8080/path/to/repository_identifier.xml
     * - Integrated static repository inside Omeka:
     * @example example.org/web_root_path/repository/repository_identifier.xml
     */
    public $parameters;
    public $status;
    public $messages;
    public $owner_id;
    public $added;
    public $modified;

    // Temporary unjsoned parameters.
    private $_parameters;

    // Temporary total of items and files.
    private $_totalItems;
    private $_totalFiles;

    protected function _initializeMixins()
    {
        $this->_mixins[] = new Mixin_Owner($this);
        $this->_mixins[] = new Mixin_Timestamp($this, 'added', 'modified');
    }

    /**
     * Get the user object.
     *
     * @return User|null
     */
    public function getOwner()
    {
        if ($this->owner_id) {
            return $this->getTable('User')->find($this->owner_id);
        }
    }

    /**
     * Returns the parameters of the folder.
     *
     * @throws UnexpectedValueException
     * @return array The parameters of the folder.
     */
    public function getParameters()
    {
        if ($this->_parameters === null) {
            $parameters = json_decode($this->parameters, true);
            if (empty($parameters)) {
                $parameters = array();
            }
            elseif (!is_array($parameters)) {
                throw new UnexpectedValueException(__('Parameters must be an array. '
                    . 'Instead, the following was given: %s.', var_export($parameters, true)));
            }
            $this->_parameters = $parameters;
        }
        return $this->_parameters;
    }

    /**
     * Returns the specified parameter of the folder.
     *
     * @param string $name
     * @return string The specified parameter of the folder.
     */
    public function getParameter($name)
    {
        $parameters = $this->getParameters();
        return isset($parameters[$name]) ? $parameters[$name] : null;
    }

    public function getProperty($property)
    {
        switch($property) {
            case 'added_username':
                $user = $this->getOwner();
                return $user
                    ? $user->username
                    : __('Anonymous');
            default:
                return parent::getProperty($property);
        }
    }

    /**
     * Sets the status of the folder.
     *
     * @param string The status of the folder.
     */
    public function setStatus($status)
    {
        $this->status = (string) $status;
    }

    /**
     * Sets parameters.
     *
     * @param array $parameters
     */
    public function setParameters($parameters)
    {
        // Check null.
        if (empty($parameters)) {
            $parameters = array();
        }
        elseif (!is_array($parameters)) {
            throw new InvalidArgumentException(__('Parameters must be an array.'));
        }
        $this->_parameters = $parameters;
    }

    /**
     * Set the specified parameter of the folder.
     *
     * @param string $name
     * @param var $value
     * @return string The specified parameter of the folder.
     */
    public function setParameter($name, $value)
    {
        // Initialize parameters if needed.
        $parameters = $this->getParameters();
        $this->_parameters[$name] = $value;
    }

    /**
     * Indicate if there was an error.
     *
     * @return boolean
     */
    public function isError()
    {
        return $this->status == ArchiveFolder_Folder::STATUS_ERROR;
    }

    /**
     * Indicate if the process of the folder has been stopped in the background.
     *
     * @return boolean
     */
    public function hasBeenStopped()
    {
        // Check the true status, that may have been updated in background.
        $currentStatus = $this->getTable('ArchiveFolder_Folder')->getCurrentStatus($this->id);
        return $currentStatus == ArchiveFolder_Folder::STATUS_STOPPED;
    }

    /**
     * Filter the form input according to some criteria.
     *
     * @todo Move part of these filter inside save().
     *
     * @param array $post
     * @return array Filtered post data.
     */
    protected function filterPostData($post)
    {
        // Remove superfluous whitespace.
        $options = array('inputNamespace' => 'Omeka_Filter');
        $filters = array(
            'uri' => array('StripTags', 'StringTrim'),
            // 'item_type_id'  => 'ForeignKey',
            'records_for_files' => 'Boolean',
        );
        $filter = new Zend_Filter_Input($filters, null, $post, $options);
        $post = $filter->getUnescaped();

        $post['uri'] = rtrim(trim($post['uri']), '/.');

        // Unset immutable or specific properties from $_POST.
        $immutable = array('id', 'identifier', 'parameters', 'status', 'messages', 'owner_id', 'added', 'modified');
        foreach ($immutable as $value) {
            unset($post[$value]);
        }

        // This filter move all parameters inside 'parameters' of the folder.
        $parameters = $post;
        // Property level.
        unset($parameters['uri']);
        unset($parameters['item_type_id']);
        // Not properties.
        unset($parameters['csrf_token']);
        unset($parameters['submit']);

        // Set default parameters if needed.
        $defaults = array(
            'unreferenced_files' => 'by_file',
            'exclude_extensions' => '',
            'element_delimiter' => ArchiveFolder_Mapping_Table::DEFAULT_ELEMENT_DELIMITER,
            'empty_value' => ArchiveFolder_Mapping_Table::DEFAULT_EMPTY_VALUE,
            'extra_parameters' => array(),
            'records_for_files' => true,
            'item_type_name' => '',
            'identifier_field' => ArchiveFolder_Importer::DEFAULT_IDFIELD,
            'action' => ArchiveFolder_Importer::DEFAULT_ACTION,
        );
        $parameters = array_merge($defaults, $parameters);

        // Manage some exceptions.

        // The repository identifier is kept for future evolutions and for the
        // compatibility with the plugin OAI-PMH Static Repository.

        // Remove the web dir when possible.
        if (strpos($post['uri'], WEB_DIR) === 0) {
            $repositoryIdentifierBase = substr($post['uri'], strlen(WEB_DIR));
        }
        // Else remove the protocol and the domain.
        elseif (parse_url($post['uri'], PHP_URL_HOST)) {
            $repositoryIdentifierBase = parse_url($post['uri'], PHP_URL_PATH);
        }
        // Else keep the full uri.
        else {
            $repositoryIdentifierBase = $post['uri'];
        }
        $repositoryIdentifierBase .= '-' . date('Ymd-His') . '-' . rtrim(strtok(substr(microtime(), 2), ' '), '0');
        $parameters['repository_identifier'] = $this->_keepAlphanumericOnly($repositoryIdentifierBase);

        $parameters['item_type_name'] = $this->_getItemTypeName($post['item_type_id']);

        $parameters['extra_parameters'] = $this->_getExtraParameters($parameters['extra_parameters']);

        if (empty($parameters['unreferenced_files'])) {
            $parameters['unreferenced_files'] = $defaults['unreferenced_files'];
        }

        // Other parameters are not changed, so save them.
        $this->setParameters($parameters);

        $post['identifier'] = $parameters['repository_identifier'];

        return $post;
    }

    /**
     * Convert a string into a list of extra parameters.
     *
     * @internal The parameters are already checked via Zend form validator.
     *
     * @param array|string $extraParameters
     * @return array
     */
    protected function _getExtraParameters($extraParameters)
    {
        if (is_array($extraParameters)) {
            return $extraParameters;
        }

        $parameters = array();

        $parametersAdded = array_values(array_filter(array_map('trim', explode(PHP_EOL, $extraParameters))));
        foreach ($parametersAdded as $parameterAdded) {
            list($paramName, $paramValue) = explode('=', $parameterAdded);
            $parameters[trim($paramName)] = trim($paramValue);
        }

        return $parameters;
    }

    /**
     * Get the local filepath of the xml file of the static repository.
     *
     * @return string
     */
    public function getLocalRepositoryFilepath()
    {
        return FILES_DIR
            . DIRECTORY_SEPARATOR . get_option('archive_folder_static_dir')
            . DIRECTORY_SEPARATOR . $this->identifier . '.xml';
    }

    /**
     * Get all archive folder records associated to this folder.
     *
     * @return array List of archive folder records, ordered by index.
     */
    public function getArchiveFolderRecords()
    {
        return $this->getTable('ArchiveFolder_Record')->findByFolder($this->id);
    }

    /**
     * Get an archive folder record by index. If index is 0, get all records.
     *
     * @param integer $index
     * @return Record|array List of archive folder records, ordered by index.
     */
    public function getArchiveFolderRecord($index)
    {
        return $this->getTable('ArchiveFolder_Record')->findByFolderAndIndex($this->id, $index);
    }

    /**
     * Get all records associated to this folder.
     *
     * @return array List of records, ordered by index.
     */
    public function getRecords()
    {
        $archiveFolderRecords = $this->getArchiveFolderRecords();
        if (empty($archiveFolderRecords)) {
            return;
        }

        $records = array();
        foreach ($archiveFolderRecords as $archiveFolderRecord) {
            $records[] = $archiveFolderRecord->getRecord();
        }
        return array_filter($records);
    }

    /**
     * Get a record by its index. If 0, return all records without index.
     *
     * @param integer $index
     * @return Record|array List of records, ordered by index.
     */
    public function getRecord($index)
    {
        $archiveFolderRecords = $this->getArchiveFolderRecord($index);
        if (empty($archiveFolderRecords)) {
            return;
        }

        // Only one record (generic case: index is not 0).
        if ($index) {
            return $archiveFolderRecords->getRecord();
        }

        $records = array();
        foreach ($archiveFolderRecords as $archiveFolderRecord) {
            $records[] = $archiveFolderRecord->getRecord();
        }
        return array_filter($records);
    }

    /**
     * Allow to set the item type name from filename (default) or to force it.
     *
     * @param string $itemTypeId
     * @return string
     */
    protected function _getItemTypeName($itemTypeId)
    {
        if (empty($itemTypeId)) {
            $itemTypeName = '';
        }
        elseif ($itemTypeId == 'default') {
            $itemTypeName = $itemTypeId;
        }
        // Check if item type exists.
        elseif (is_numeric($itemTypeId)) {
            $itemType = get_record_by_id('ItemType', $itemTypeId);
            if ($itemType) {
                $itemTypeName = $itemType->name;
            }
        }
        return $itemTypeName;
    }

    /**
     * Executes before the record is saved.
     *
     * @param array $args
     */
    protected function beforeSave($args)
    {
        $values = $this->getParameters();
        $this->parameters = version_compare(phpversion(), '5.4.0', '<')
            ? json_encode($values)
            : json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (is_null($this->messages)) {
            $this->messages = '';
        }

        if (is_null($this->owner_id)) {
            $this->owner_id = 0;
        }
    }

    /**
     * @todo Put some of these checks in the form.
     */
    protected function _validate()
    {
        $uri = rtrim(trim($this->uri), '/.');
        if (empty($uri)) {
            $this->addError('uri', __('An url or a path is required to add a folder.'));
        }
        else {
            $scheme = parse_url($uri, PHP_URL_SCHEME);
            if (!(in_array($scheme, array('http', 'https', /*'ftp', 'sftp',*/ 'file')) || $uri[0] == '/')) {
                $this->addError('uri', __('The url or path should be written in a standard way.'));
            }
        }

        if (empty($this->identifier)) {
            $this->addError('repository_identifier', __('There is no repository identifier.'));
        }

        if (empty($this->id)) {
            $folder = $this->getTable('ArchiveFolder_Folder')->findByUri($this->uri);
            if (!empty($folder)) {
               $this->addError('uri', __('The folder for uri "%s" exists already.', $this->uri));
            }

            $folder = $this->getTable('ArchiveFolder_Folder')->findByIdentifier($this->identifier);
            if (!empty($folder)) {
               $this->addError('repository_identifier', __('The repository identifier "%s" exists already.', $this->identifier));
            }

            if (trim($this->status) == '') {
                $this->status = ArchiveFolder_Folder::STATUS_ADDED;
            }
        }

        if (!in_array($this->status, array(
                ArchiveFolder_Folder::STATUS_ADDED,
                ArchiveFolder_Folder::STATUS_RESET,
                ArchiveFolder_Folder::STATUS_QUEUED,
                ArchiveFolder_Folder::STATUS_PROGRESS,
                ArchiveFolder_Folder::STATUS_PAUSED,
                ArchiveFolder_Folder::STATUS_STOPPED,
                ArchiveFolder_Folder::STATUS_KILLED,
                ArchiveFolder_Folder::STATUS_COMPLETED,
                ArchiveFolder_Folder::STATUS_DELETED,
                ArchiveFolder_Folder::STATUS_ERROR,
            ))) {
            $this->addError('status', __('The status "%s" does not exist.', $this->status));
        }
    }

    public function process($type = ArchiveFolder_Builder::TYPE_CHECK)
    {
        // Builder.
        if (in_array($type, array(
                ArchiveFolder_Builder::TYPE_CHECK,
                ArchiveFolder_Builder::TYPE_UPDATE,
             ))) {
             $this->build($type);
        }
        // Importer.
        else {
             $this->import();
        }
    }

    public function build($type = ArchiveFolder_Builder::TYPE_CHECK)
    {
        $message = __('Process "%s" started.', $type);
        $this->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_DEBUG);
        _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s', $this->id, $this->uri, $message), Zend_Log::DEBUG);

        $this->setStatus(ArchiveFolder_Folder::STATUS_PROGRESS);
        $this->save();

        // Create collection if it is set to be the name of the repository. It
        // can't be removed automatically.
        $this->_createCollectionIfNeeded();

        $builder = new ArchiveFolder_Builder();
        try {
            $documents = $builder->process($this, $type);
        } catch (ArchiveFolder_BuilderException $e) {
            $this->setStatus(ArchiveFolder_Folder::STATUS_ERROR);
            $message = __('Error during process: %s', $e->getMessage());
            $this->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_ERROR);
            _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s', $this->id, $this->uri, $message), Zend_Log::ERR);
            return;
        } catch (Exception $e) {
            $this->setStatus(ArchiveFolder_Folder::STATUS_ERROR);
            $message = __('Unknown error during process: %s', $e->getMessage());
            $this->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_ERROR);
            _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s', $this->id, $this->uri, $message), Zend_Log::ERR);
            return;
        }

        if ($this->hasBeenStopped()) {
            $message = __('Process has been stopped by user.');
            $this->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_INFO);
            _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s', $this->id, $this->uri, $message));
            return;
        }

        $message = $this->_checkDocuments($documents);
        $this->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_NOTICE);
        _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s', $this->id, $this->uri, $message));

        switch ($type) {
            case ArchiveFolder_Builder::TYPE_CHECK:
                _log('[ArchiveFolder] ' . __('Folder #%d [%s]: Check finished.', $this->id, $this->uri));
                $this->setStatus(ArchiveFolder_Folder::STATUS_COMPLETED);
                $this->save();
                break;
            case ArchiveFolder_Builder::TYPE_UPDATE:
                $this->setStatus(ArchiveFolder_Folder::STATUS_COMPLETED);
                $message = __('List of records ready.');
                $this->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_NOTICE);
                _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s', $this->id, $this->uri, $message));

                $this->_postProcess();
                break;
        }
    }

    /**
     * The post process is the import process (harvesting) if set.
     *
     * @internal This process should be managed as a job.
     */
    private function _postProcess()
    {
        $folder = $this;

        // TODO Manage all status.
        if ($folder->status != ArchiveFolder_Folder::STATUS_COMPLETED) {
            $message = __("The process can't be launched, because the folder is not ready.");
            $this->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_NOTICE);
            _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s', $this->id, $this->uri, $message));
            return false;
        }

        // Reset the total of imported records.
        $this->setParameter('imported_records', 0);

        $jobDispatcher = Zend_Registry::get('bootstrap')->getResource('jobs');
        $jobDispatcher->setQueueName(ArchiveFolder_UpdateJob::QUEUE_NAME);

        $options = array(
            'folderId' => $this->id,
            'processType' => 'import',
        );

        $flash = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');

        // Short dispatcher if user wants it.
        if (get_option('archive_folder_short_dispatcher')) {
            try {
                $jobDispatcher->send('ArchiveFolder_UpdateJob', $options);
            } catch (Exception $e) {
                $message = __('Error when processing folder.');
                $folder->setStatus(ArchiveFolder_Folder::STATUS_ERROR);
                $folder->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_ERROR);
                _log('[ArchiveFolder] ' . __('Folder "%s" (#%d): %s',
                    $folder->uri, $folder->id, $message), Zend_Log::ERR);
                $flash->addMessage($message, 'error');
                return false;
            }

            $message = __('Folder "%s" has been processed.', $folder->uri);
            $flash->addMessage($message, 'success');
            return true;
        }

        // Normal dispatcher for long processes.
        $jobDispatcher->sendLongRunning('ArchiveFolder_UpdateJob', $options);
        $message = __('Folder "%s" is being processed.', $folder->uri)
            . ' ' . __('This may take a while. Please check below for status.');
        $flash->addMessage($message, 'success');
        return true;
    }

    /**
     * Check the list of documents.
     *
     * @param array $documents Documents to checks.
     * @return string Message.
     */
    protected function _checkDocuments($documents)
    {
        if (empty($documents)) {
            $message = __('No document or resource unavailable.');
        }
        // Computes total.
        else {
            $this->countRecordsOfDocuments($documents);
            $message = __('Result: %d items and %d files.',
                $this->_totalItems, $this->_totalFiles);
        }
        return $message;
    }

    /**
     * Count the total of documents (all or by type).
     *
     * @param null|array $documents If null, computes for the existing
     * documents.
     * @param string $recordType "Item" or "File", else all types.
     * @return integer The total of the selected record type.
     */
    public function countRecordsOfDocuments($documents = null, $recordType = null)
    {
        // Docs shouldn't be a null.
        if (empty($documents)) {
            $documents = array();
        }

        $type =  ucfirst(strtolower($recordType));
        $totalDocuments = 0;
        switch ($type) {
            case 'Item':-
                $totalDocuments = count($documents);
                $this->_totalItems = $totalDocuments;
                break;
            // In Omeka, files aren't full records because they depend of items.
            case 'File':
                foreach ($documents as $document) {
                    if (isset($document['files'])) {
                        $totalDocuments += count($document['files']);
                    }
                }
                $this->_totalFiles = $totalDocuments;
                break;
            default:
                $totalItems = $this->countRecordsOfDocuments($documents, 'Item');
                $totalFiles = $this->countRecordsOfDocuments($documents, 'File');
                $totalDocuments = $totalItems + $totalFiles;
                break;
        }

        return $totalDocuments;
    }

    /**
     * Create collection if it is set to be the name of the repository and if it
     * is not already created.
     *
     * @return integer|null The id of the collection, or null if no collection
     */
    protected function _createCollectionIfNeeded()
    {
        $collectionId = $this->getParameter('collection_id');
        if (empty($collectionId)) {
            return null;
        }

        // Check if collection exists.
        if (is_numeric($collectionId)) {
            $collection = get_record_by_id('Collection', $collectionId);
            if ($collection) {
                return (integer) $collectionId;
            }
        }

        // Collection doesn't exist, so set it with the name.
        if ($collectionId != 'default') {
            return null;
        }

        // Prepare collection.
        $metadata = array(
            'public' => $this->getParameter('records_are_public'),
            'featured' => $this->getParameter('records_are_featured'),
        );

        // The title of the collection is the url of the repository.
        $repositoryName = $this->uri;

        $elementTexts = array();
        $elementTexts['Dublin Core']['Title'][] =
            array('text' => $repositoryName, 'html' => false);
        $elementTexts['Dublin Core']['Identifier'][] =
            array('text' => $this->uri, 'html' => false);

        $collection = insert_collection($metadata, $elementTexts);

        // Save the collection as parameter of the folder.
        $this->setParameter('collection_id', $collection->id);

        return $collection->id;
    }

    /**
     * Import the first record that is not yet imported.
     *
     * @internal The loop is managed by the job.
     * @see ArchiveFolder_UpdateJob::perform()
     *
     * @return void
     */
    public function import()
    {
        $total = $this->countRecords();
        if (!$total) {
            $message = __('This folder has no record to process.');
            $this->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_NOTICE);
            _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s', $this->id, $this->uri, $message), Zend_Log::DEBUG);
            return;
        }

        $toImport = $this->countRecordsToImport();
        if (!$toImport) {
            $message = __('All %d records have already been processed.', $total);
            $this->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_NOTICE);
            _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s', $this->id, $this->uri, $message), Zend_Log::DEBUG);
            return;
        }

        if ($this->status != ArchiveFolder_Folder::STATUS_COMPLETED) {
            $message = __('Folder is not ready to be processed.');
            $this->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_NOTICE);
            _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s', $this->id, $this->uri, $message), Zend_Log::DEBUG);
            return;
        }

        $importeds = $this->countImportedRecords();
        $message = __('Process of record #%d/%d started.', $importeds + 1, $total);
        $this->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_DEBUG);
        _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s', $this->id, $this->uri, $message), Zend_Log::DEBUG);

        $this->setStatus(ArchiveFolder_Folder::STATUS_PROGRESS);
        $this->save();

        $importer = new ArchiveFolder_Importer();

        try {
            $result = $importer->process($this);
        } catch (ArchiveFolder_ImporterException $e) {
            $this->setStatus(ArchiveFolder_Folder::STATUS_ERROR);
            $message = __('Error during import of record #%d/%d: %s', $importeds + 1, $total, $e->getMessage());
            $this->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_ERROR);
            _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s', $this->id, $this->uri, $message), Zend_Log::ERR);
            return;
        } catch (Exception $e) {
            $this->setStatus(ArchiveFolder_Folder::STATUS_ERROR);
            $message = __('Unknown error during import of record #%d/%d: %s', $importeds + 1, $total, $e->getMessage());
            $this->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_ERROR);
            _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s', $this->id, $this->uri, $message), Zend_Log::ERR);
            return;
        }

        // Check if there was an error.
        if ($result) {
            // Update the count to avoid to import the same record.
            $hasBeenStopped = $this->hasBeenStopped();
            $this->setParameter('imported_records', $importeds + 1);
            $this->setStatus($hasBeenStopped
                ? ArchiveFolder_Folder::STATUS_STOPPED
                : ArchiveFolder_Folder::STATUS_COMPLETED);
            $message = __('Process of record #%d/%d finished.', $importeds + 1, $total);
            $this->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_DEBUG);
            _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s', $this->id, $this->uri, $message), Zend_Log::DEBUG);
            if ($importeds + 1 == $total) {
                $message = __('All %d records have been processed.', $total);
                $this->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_INFO);
                _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s', $this->id, $this->uri, $message), Zend_Log::INFO);
            }
        }
        // Error: no import.
        else {
            $this->setStatus(ArchiveFolder_Folder::STATUS_ERROR);
            $message = __('Process of record #%d/%d failed.', $importeds + 1, $total);
            $this->addMessage($message, ArchiveFolder_Folder::MESSAGE_CODE_ERROR);
            _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s', $this->id, $this->uri, $message, Zend_Log::WARN));
        }
    }

    public function countRecords()
    {
        $total = $this->getParameter('total_records');
        if (is_null($total)) {
            $total = $this->_countRecords();
        }
        return $total;
    }

    public function countImportedRecords()
    {
        return (integer) $this->getParameter('imported_records');
    }

    public function countRecordsToImport()
    {
        $records = $this->countRecords();
        $importeds = $this->countImportedRecords();
        return $records - $importeds;
    }

    /**
     * Count all the records in the repository, at any level.
     *
     * @return integer|boolean
     */
    protected function _countRecords()
    {
        $reader = $this->_getXmlReader();
        if (!$reader) {
            return false;
        }

        $total = 0;
        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT
                    && $reader->name == 'record'
                ) {
                $total++;
                // Count only first level records (items).
                // $reader->next();
            }
        }

        $reader->close();

        return $total;
    }

    protected function _getXmlReader()
    {
        // Prepare the xml reader for the existing static repository.
        // Don't use a static value to allow tests.
        $localRepositoryFilepath = $this->getLocalRepositoryFilepath();
        if (!file_exists($localRepositoryFilepath)) {
            return false;
        }

        // Read the xml from the beginning.
        $reader = new XMLReader;
        $result = $reader->open($localRepositoryFilepath, null, LIBXML_NSCLEAN);
        if (!$result) {
            return false;
        }

        return $reader;
    }

    /**
     * All of the custom code for deleting a folder.
     */
    protected function _delete()
    {
        $this->_deleteRecords();
    }

    /**
     * Delete archive folder records associated with the folder.
     */
    protected function _deleteRecords()
    {
        $archiveRecordsToDelete = $this->getArchiveFolderRecords();

        foreach ($archiveRecordsToDelete as $record) {
            $record->delete();
        }
    }

    public function addMessage($message, $messageCode = null, $delimiter = PHP_EOL)
    {
        if (strlen($this->messages) == 0) {
            $delimiter = '';
        }
        $date = date('Y-m-d H:i:s');
        $messageCodeText = $this->_getMessageCodeText($messageCode);
        $this->messages .= $delimiter . "[$date] $messageCodeText: $message";
        $this->save();
    }

    /**
     * Return a message code text corresponding to its constant.
     *
     * @param int $messageCode
     * @return string
     */
    private function _getMessageCodeText($messageCode)
    {
        $messagesCodes = array(
            ArchiveFolder_Folder::MESSAGE_CODE_ERROR => __('Error'),
            ArchiveFolder_Folder::MESSAGE_CODE_NOTICE => __('Notice'),
            ArchiveFolder_Folder::MESSAGE_CODE_INFO => __('Info'),
            ArchiveFolder_Folder::MESSAGE_CODE_DEBUG => __('Debug'),
        );
        return isset($messagesCodes[$messageCode])
            ? $messagesCodes[$messageCode]
            : $messagesCodes[ArchiveFolder_Folder::MESSAGE_CODE_NOTICE];
    }

    /**
     * Declare the representative model as relating to the record ACL resource.
     *
     * Required by Zend_Acl_Resource_Interface.
     *
     * @return string
     */
    public function getResourceId()
    {
        return 'ArchiveFolder_Folders';
    }

    /**
     * Clean a string to keep only standard alphanumeric characters and "-_.".
     *
     * @todo Make fully compliant with specs.
     *
     * @see http://www.openarchives.org/OAI/2.0/guidelines-oai-identifier.htm
     *
     * @param string $string Dirty string.
     * @return string Clean string.
     */
    private function _keepAlphanumericOnly($string)
    {
        return preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $string);
    }

    /**
     * Clean a string to keep only standard alphanumeric characters and "-_./".
     * Spaces are replaced with '_'.
     *
     * @todo Make fully compliant with specs.
     *
     * @see http://www.openarchives.org/OAI/2.0/guidelines-oai-identifier.htm
     *
     * @param string $string Dirty string.
     * @return string Clean string.
     */
    private function _keepAlphanumericOnlyForDir($string)
    {
        return preg_replace("/[^a-zA-Z0-9\-_\.\/]/", '', str_replace(' ', '_', $string));
    }
}
