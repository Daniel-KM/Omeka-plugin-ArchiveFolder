<?php

/**
 * @package ArchiveFolder
 */
class ArchiveFolder extends Omeka_Record_AbstractRecord implements Zend_Acl_Resource_Interface
{
    /**
     * Notice message code, used for status messages.
     */
    const MESSAGE_CODE_NOTICE = 5;

    /**
     * Error message code, used for status messages.
     */
    const MESSAGE_CODE_ERROR = 3;

    const STATUS_ADDED = 'added';
    const STATUS_RESET = 'reset';
    const STATUS_QUEUED = 'queued';
    const STATUS_PROGRESS = 'progress';
    const STATUS_PAUSED = 'paused';
    const STATUS_STOPPED = 'stopped';
    const STATUS_KILLED = 'killed';
    const STATUS_COMPLETED = 'completed';
    const STATUS_DELETED = 'deleted';
    const STATUS_ERROR = 'error';

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

    /**
     * Indicate if the process of the folder has been stopped.
     *
     * @return boolean
     */
    public function hasBeenStopped()
    {
        $currentStatus = $this->getTable('ArchiveFolder')->getCurrentStatus($this->id);
        return $currentStatus == ArchiveFolder::STATUS_STOPPED;
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
     * Prepare parameters from a form, in order to make them coherent.
     *
     * @param array $parameters
     */
    public function prepareParameters($parameters)
    {
        $this->uri = rtrim(trim($this->uri), '/.');

        // Default parameters if not set.
        $defaults = array(
            'unreferenced_files' => 'by_file',
            'exclude_extensions' => '',
            'element_delimiter' => '',
            'fill_ocr_text' => false,
            'fill_ocr_data' => false,
            'fill_ocr_process' => false,
            'records_for_files' => false,
            'item_type_name' => '',
        );

        $parameters = array_merge($defaults, $parameters);

        // Manage empty values for some parameters.
        foreach (array(
                'unreferenced_files',
            ) as $value) {
            if (empty($parameters[$value])) {
                $parameters[$value] = $defaults[$value];
            }
        }

        // Manage some exceptions.

        // The repository identifier is kept for future evolutions and for the
        // compatibility with the plugin OAI-PMH Static Repository.

        // Remove the web dir when possible.
        if (strpos($this->uri, WEB_DIR) === 0) {
            $repositoryIdentifierBase = substr($this->uri, strlen(WEB_DIR));
        }
        // Else remove the protocol and the domain.
        elseif (parse_url($this->uri, PHP_URL_HOST)) {
            $repositoryIdentifierBase = parse_url($foo, PHP_URL_PATH);
        }
        // Else keep the full uri.
        else {
            $repositoryIdentifierBase = $this->uri;
        }
        $repositoryIdentifierBase .= '-' . time() . rtrim(strtok(substr(microtime(), 2), ' '), '0');
        $parameters['repository_identifier'] = $this->_keepAlphanumericOnly($repositoryIdentifierBase);

        $parameters['records_for_files'] = (boolean) $parameters['records_for_files'];

        $parameters['item_type_name'] = $this->_getItemTypeName();

        // Other parameters are not changed, so save them.
        $this->setParameters($parameters);

        $this->identifier = $parameters['repository_identifier'];
    }

    /**
     * Get url of the static repository (scheme + repository + ".xml").
     *
     * @example http://institution.org:8080/path/to/repository_identifier.xml
     * @example http://example.org/repository/repository_identifier.xml
     *
     * @return string
     */
    public function getStaticRepositoryUrl()
    {
        return $this->getParameter('repository_url');
    }

    /**
     * Get the base url of the static repository (gateway + static repository
     * url without scheme).
     *
     * @example http://example.org/gateway/institution.org%3A8080/path/to/repository_identifier.xml
     * @example http://example.org/gateway/example.org/repository/repository_identifier.xml
     *
     * @return string
     */
    public function getStaticRepositoryBaseUrl()
    {
        return $this->getParameter('repository_base_url');
    }

    /**
     * Get the url to the folder of files (original uri for remote folder, files
     * one for local). This will be used to build quickly the path for local
     * files.
     *
     * @example http://institution.org:8080/path/to/folder/
     * @example http://example.org/repository/repository_identifier/
     *
     * @return string
     */
    public function getStaticRepositoryUrlFolder()
    {
        return $this->getParameter('repository_folder');
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
     * Allow to set the item type name from filename (default) or to force it.
     *
     * @return string
     */
    protected function _getItemTypeName()
    {
        $itemTypeId = $this->_postData['item_type_id'];
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
            $folder = $this->getTable('ArchiveFolder')->findByUri($this->uri);
            if (!empty($folder)) {
               $this->addError('uri', __('The folder for uri "%s" exists already.', $this->uri));
            }

            $folder = $this->getTable('ArchiveFolder')->findByIdentifier($this->identifier);
            if (!empty($folder)) {
               $this->addError('repository_identifier', __('The repository identifier "%s" exists already.', $this->identifier));
            }

            if (trim($this->status) == '') {
                $this->status = ArchiveFolder::STATUS_ADDED;
            }
        }

        if (!in_array($this->status, array(
                ArchiveFolder::STATUS_ADDED,
                ArchiveFolder::STATUS_RESET,
                ArchiveFolder::STATUS_QUEUED,
                ArchiveFolder::STATUS_PROGRESS,
                ArchiveFolder::STATUS_PAUSED,
                ArchiveFolder::STATUS_STOPPED,
                ArchiveFolder::STATUS_KILLED,
                ArchiveFolder::STATUS_COMPLETED,
                ArchiveFolder::STATUS_DELETED,
                ArchiveFolder::STATUS_ERROR,
            ))) {
            $this->addError('status', __('The status "%s" does not exist.', $this->status));
        }
    }

    public function process($type = ArchiveFolder_Builder::TYPE_CHECK)
    {
        _log('[ArchiveFolder] '. __('Folder #%d [%s]: Process started.', $this->id, $this->uri));

        $this->setStatus(ArchiveFolder::STATUS_PROGRESS);
        $this->save();

        // Create collection if it is set to be the name of the repository. It
        // can't be removed automatically.
        $this->_createCollectionIfNeeded();

        $builder = new ArchiveFolder_Builder();
        try {
            $documents = $builder->process($this, $type);
        } catch (ArchiveFolder_BuilderException $e) {
            $this->setStatus(ArchiveFolder::STATUS_ERROR);
            $message = __('Error during process: %s', $e->getMessage());
            $this->addMessage($message, ArchiveFolder::MESSAGE_CODE_ERROR);
            _log('[ArchiveFolder] '. __('Folder #%d [%s]: %s', $this->id, $this->uri, $message), Zend_Log::ERR);
            return;
        } catch (Exception $e) {
            $this->setStatus(ArchiveFolder::STATUS_ERROR);
            $message = __('Unknown error during process: %s', $e->getMessage());
            $this->addMessage($message, ArchiveFolder::MESSAGE_CODE_ERROR);
            _log('[ArchiveFolder] '. __('Folder #%d [%s]: %s', $this->id, $this->uri, $message), Zend_Log::ERR);
            return;
        }

        if ($this->hasBeenStopped()) {
            $message = __('Process has been stopped by user.');
            $this->addMessage($message);
            _log('[ArchiveFolder] '. __('Folder #%d [%s]: %s', $this->id, $this->uri, $message));
            return;
        }

        $message = $this->_checkDocuments($documents);
        $this->addMessage($message, ArchiveFolder::MESSAGE_CODE_NOTICE);
        _log('[ArchiveFolder] '. __('Folder #%d [%s]: %s', $this->id, $this->uri, $message));

        switch ($type) {
            case ArchiveFolder_Builder::TYPE_CHECK:
                _log('[ArchiveFolder] '. __('Folder #%d [%s]: Check finished.', $this->id, $this->uri));
                $this->setStatus(ArchiveFolder::STATUS_COMPLETED);
                $this->save();
                break;
            case ArchiveFolder_Builder::TYPE_UPDATE:
                $this->setStatus(ArchiveFolder::STATUS_COMPLETED);
                $message = __('Update finished.');
                $this->addMessage($message, ArchiveFolder::MESSAGE_CODE_NOTICE);
                _log('[ArchiveFolder] '. __('Folder #%d [%s]: %s', $this->id, $this->uri, $message));

                $this->_postProcess();
                break;
        }
    }

    /**
     * The post process is the import process (harvesting) if set.
     *
     * @internal This process should be managed as a job.
     *
     * @param ArchiveFolder $folder
     */
    private function _postProcess()
    {
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
            $this->countRecords($documents);
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
    public function countRecords($documents = null, $recordType = null)
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
                $totalItems = $this->countRecords($documents, 'Item');
                $totalFiles = $this->countRecords($documents, 'File');
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
        $repositoryName = $this->getParameter('repository_name');

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
        switch ($messageCode) {
            case ArchiveFolder::MESSAGE_CODE_ERROR:
                return __('Error');
            case ArchiveFolder::MESSAGE_CODE_NOTICE:
            default:
                return __('Notice');
        }
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
        return 'ArchiveFolder';
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
        return preg_replace("/[^a-zA-Z0-9\-_\.]/", '', $string);
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
