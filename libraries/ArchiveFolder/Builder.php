<?php

/**
 * ArchiveFolder_Builder class
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Builder
{
    const TYPE_CHECK = 'check';
    const TYPE_UPDATE = 'update';

    const XML_INDENT = true;

    // Options for the process.
    protected $_folder;
    protected $_type = self::TYPE_CHECK;

    // Parameters of the repository.
    protected $_parameters;

    // The oai identifier class.
    protected $_oaiIdentifier;

    // The mappings classes.
    protected $_mappings;

    // List of the used metadata formats for this folder.
    protected $_metadataFormats;

    protected $_transferStrategy;

    /**
     * Lists of files are associative arrays where the key is the full path.
     */
    // TODO Keep iterator or set one if not available.
    // List of repertories available in the folder.
    protected $_folders;
    // List of content files available in the folder.
    protected $_files;
    // List of metadata files available in the folder.
    protected $_metadataFiles;

    // List of documents, according to parameters:
    // array List of documents (items)
    //     index => Index is used as order; starts from 1 internally when ready
    //         name => relative path (sub-directory) or name (metadata file)
    //         oai_id => oai id of the item (set internally)
    //         metadata => if any, array of elements
    //         extra => if any, array of unrecognized data, as tags or item type
    //         format_xml => if any, format of xml metadata to include directly
    //         xml => if any, the content to include for the format
    //         files => ordered array of files attached to the document if any
    //             index => array Index is used as order and starts from 1
    //                 path => absolute filepath (local or http)
    //                 name => filepath relative to the main folder, else url
    //                 oai_id => oai id of the file (set internally)
    //                 metadata => if any, array of elements
    //                 extra => if any, array of unrecognized data
    //                 format_xml => format of xml metadata to include directly
    //                 xml => if any, the content to include for the format
    // In metadata files, the file path may be relative. The mapping class may
    // be used to convert it to absolute path and to relative path name. The
    // index order and the oai_id are set internally.
    // Unrecognized key/values are saved in the "extra" array. They will be
    // included in the static repository only if a format or a hook manage them.
    // By default none are managed, even "tags" and "item type", that are not
    // standard metadata: tags may be replaced by Dublin Core : Subject or
    // Coverage and item types by Dublin Core : Type.
    protected $_documents;

    // Used to create the xml for the static repository.
    protected $_xmlpathTemp;
    // Main writer, used for the main static repository.
    protected $_writer;
    // Writer used for each document.
    protected $_documentWriter;

    // TODO The progress of the process.

    /**
     * Process update of a folder.
     *
     * @param ArchiveFolder $folder The folder to process.
     * @param string $type The type of process.
     * @return List of documents.
     */
    public function process(ArchiveFolder $folder, $type = self::TYPE_CHECK)
    {
        $this->_folder = $folder;
        $this->_type = $type;

        // This simplifies the use of parameters.
        $this->_parameters = $this->_folder->getParameters();

        // It is recommended that all dates are GMT.
        $timezone = date_default_timezone_get();
        @date_default_timezone_set('GMT');
        $time = time();
        $this->_parameters['datestamp'] = date('Y-m-d', $time);
        $this->_parameters['timestamp'] = date('Y-m-d\TH:i:s\Z', $time);
        @date_default_timezone_set($timezone);

        try {
            $this->_checkFolderUri();
        } catch (ArchiveFolder_BuilderException $e) {
            throw new ArchiveFolder_BuilderException($e->getMessage());
        } catch (Exception $e) {
            throw new ArchiveFolder_BuilderException(__('The folder uri or the transfer mode cannot be checked: %s', $e->getMessage()));
        }

        if (empty($this->_transferStrategy)) {
            throw new ArchiveFolder_BuilderException(__('The format of the uri is not managed.'));
        }

        $this->_parameters['transfer_strategy'] = $this->_transferStrategy;

        if ($this->_folder->hasBeenStopped()) return;

        try {
            $this->_checkOaiIdentifier();
        } catch (ArchiveFolder_BuilderException $e) {
            throw new ArchiveFolder_BuilderException($e->getMessage());
        } catch (Exception $e) {
            throw new ArchiveFolder_BuilderException(__('Identifier cannot be built: %s', $e->getMessage()));
        }

        try {
            $this->_checkMappings();
        } catch (ArchiveFolder_BuilderException $e) {
            throw new ArchiveFolder_BuilderException($e->getMessage());
        } catch (Exception $e) {
            throw new ArchiveFolder_BuilderException(__('Mappings cannot be checked: %s', $e->getMessage()));
        }

        try {
            $this->_checkMetadataFormats();
        } catch (ArchiveFolder_BuilderException $e) {
            throw new ArchiveFolder_BuilderException($e->getMessage());
        } catch (Exception $e) {
            throw new ArchiveFolder_BuilderException(__('Formats cannot be checked: %s', $e->getMessage()));
        }

        if ($this->_folder->hasBeenStopped()) return;

        try {
            $this->_listFolderAndFiles();
        } catch (Exception $e) {
            throw new ArchiveFolder_BuilderException(__('Files cannot be checked: %s', $e->getMessage()));
        }

        if (empty($this->_folders) || empty($this->_files)) {
            throw new ArchiveFolder_BuilderException(__('No folder and no file found.')
                . ' ' . __('Check your rights and your configuration.'));
        }

        if ($this->_folder->hasBeenStopped()) return;

        try {
            $this->_checkFiles();
        } catch (Exception $e) {
            throw new ArchiveFolder_BuilderException(__('Some extensions of paths cannot be checked: %s', $e->getMessage()));
        }

        if (empty($this->_folders) || empty($this->_files)) {
            throw new ArchiveFolder_BuilderException(__('No folder and no file found.')
                . ' ' . __('Check rights, allowed paths, extensions and configuration.'));
        }

        if ($this->_folder->hasBeenStopped()) return;

        try {
            $this->_listDocuments();
        } catch (ArchiveFolder_BuilderException $e) {
            throw new ArchiveFolder_BuilderException($e->getMessage());
        } catch (Exception $e) {
            throw new ArchiveFolder_BuilderException(__('The static repository cannot be built: %s', $e->getMessage()));
        }

        if ($this->_folder->hasBeenStopped()) return;

        try {
            $this->_checkDocuments();
        } catch (Exception $e) {
            throw new ArchiveFolder_BuilderException(__('Some documents cannot be imported: %s', $e->getMessage()));
        }

        if (empty($this->_documents)) {
            throw new ArchiveFolder_BuilderException(__('No folder and no file found.')
                . ' ' . __('Check rights, allowed paths, extensions and configuration.'));
        }

        if ($this->_folder->hasBeenStopped()) return;

        try {
            $this->_createOaiIdentifiers();
        } catch (ArchiveFolder_BuilderException $e) {
            throw new ArchiveFolder_BuilderException($e->getMessage());
        } catch (Exception $e) {
            throw new ArchiveFolder_BuilderException(__('The oai identifiers cannot be built: %s', $e->getMessage()));
        }

        if ($this->_folder->hasBeenStopped()) return;

        switch ($type) {
            case self::TYPE_CHECK :
                break;
            case self::TYPE_UPDATE :
                if (empty($this->_folder->identifier)) {
                    throw new ArchiveFolder_BuilderException(__('The repository identifier is not defined.'));
                }

                // Create the cache for local files and remote files that need it.
                // The original structure is not modified. To cache before the
                // build of the static repository xml file makes it easier.
                if (!$this->_getParameter('repository_remote')) {
                    try {
                        $this->_cacheFilesIntoLocalRepository();
                    } catch (ArchiveFolder_BuilderException $e) {
                        throw new ArchiveFolder_BuilderException($e->getMessage());
                    } catch (Exception $e) {
                        throw new ArchiveFolder_BuilderException(__('An error occurs when the files are cached locally: %s', $e->getMessage()));
                    }
                }

                if ($this->_folder->hasBeenStopped()) return;

                // The main process.
                $this->_createStaticRepository();

                if ($this->_folder->hasBeenStopped()) return;

                try {
                    $this->_saveStaticRepository();
                } catch (ArchiveFolder_BuilderException $e) {
                    throw new ArchiveFolder_BuilderException($e->getMessage());
                } catch (Exception $e) {
                    throw new ArchiveFolder_BuilderException(__('An error occurs when the file of the static repository was saved: %s', $e->getMessage()));
                }
                break;
        }

        return $this->_documents;
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
     * Check the folder uri and set the transfer strategy.
     */
    protected function _checkFolderUri()
    {
        $result = $this->_isUriAllowed($this->_folder->uri);
        if ($result !== true) {
            throw new ArchiveFolder_BuilderException($result);
        }

        // The scheme has just been checked above.
        $scheme = parse_url($this->_folder->uri, PHP_URL_SCHEME);
        $this->_transferStrategy = in_array($scheme, array('http', 'https', 'ftp', 'sftp'))
            ? 'Url'
            : 'Filesystem';

        if (!$this->_isUriAvailable($this->_folder->uri)) {
            throw new ArchiveFolder_BuilderException(__('The folder is not readable or not available.'));
        }
    }

    /**
     * Prepare and check the oai identifier.
     *
     * @return boolean
     */
    protected function _checkOaiIdentifier()
    {
        $oaiIdentifiers = apply_filters('archive_folder_oai_identifiers', array());

        // Check the selected identifier.
        $identifierFormat = $this->_getParameter('oai_identifier_format');
        if (!isset($oaiIdentifiers[$identifierFormat])) {
            throw new ArchiveFolder_BuilderException(__('OAI identifier format"%s" is missing.', $identifierFormat));
        }

        $class = $oaiIdentifiers[$identifierFormat]['class'];
        if (!class_exists($class)) {
            throw new ArchiveFolder_BuilderException(__('OAI identifier class "%s" is missing.', $class));
        }

        $this->_oaiIdentifier = new $class;
        $this->_oaiIdentifier->setFolderData($this->_folder->uri, $this->_parameters);

        return true;
    }

    /**
     * Prepare and check the mapping of metadata files.
     *
     * @return boolean
     */
    protected function _checkMappings()
    {
        $mappings = apply_filters('archive_folder_mappings', array());

        // Check the mappings.
        foreach ($mappings as $name => $mapping) {
            $class = $mapping['class'];
            if (!class_exists($class)) {
                throw new ArchiveFolder_BuilderException(__('Mapping class "%s" is missing.', $class));
            }
            $this->_mappings[$name] = new $class($this->_folder->uri, $this->_parameters);
        }

        return true;
    }

    /**
     * Prepare and check the formats.
     *
     * @return boolean
     */
    protected function _checkMetadataFormats()
    {
        $metadataFormats = apply_filters('archive_folder_formats', array());

        // Keep only formats that are wanted for this repository.
        $this->_metadataFormats = array();
        foreach ($metadataFormats as $name => $format) {
            // Keep only existing formats.
            if (in_array($name, $this->_getParameter('metadata_formats'))) {
                $class = $format['class'];
                if (!class_exists($class)) {
                    throw new ArchiveFolder_BuilderException(__('Format class "%s" is missing.', $class));
                }
                $this->_metadataFormats[$format['prefix']] = new $class($this->_folder->uri, $this->_parameters);
            }
        }

        // "oai_dc" is the only required format.
        if (!isset($this->_metadataFormats['oai_dc'])) {
            throw new ArchiveFolder_BuilderException(__('Format "oai_dc" is required.'));
        }

        return true;
    }

    /**
     * List folders and files of the folder.
     *
     * @todo List only readable folders and files.
     * @todo Uses Zend tools?
     */
    protected function _listFolderAndFiles()
    {
        // Quickly list files on the file system if possible.
        $scheme = parse_url($this->_folder->uri, PHP_URL_SCHEME);
        if (class_exists('RecursiveDirectoryIterator')
                && ($scheme == 'file' || $this->_folder->uri[0] == '/')
            ) {
            $result = $this->_iterateDirectory($this->_folder->uri);
        }
        // For any other list.
        else {
            $result = $this->_listDirectory($this->_folder->uri);

            // Add the base path to the result, with the short name.
            if ($result) {
                $result['dirnames'][$this->_folder->uri . '/'] = './';
                ksort($result['dirnames']);
            }
        }

        if ($result) {
            $this->_folders = $result['dirnames'];
            $this->_files = $result['filenames'];
        }
    }

    /**
     * Remove files outside of the foder, with black-listed extensions or not in
     * the Omeka white-list.
     */
    protected function _checkFiles()
    {
        $unsetExtensions = array();
        $unsets = array();
        foreach ($this->_files as $filepath => $file) {
            if (!$this->_isExtensionAllowed($filepath)) {
                unset($this->_files[$filepath]);
                $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
                $unsetExtensions[$extension] = isset($unsetExtensions[$extension]) ? ++$unsetExtensions[$extension] : 1;
                continue;
            }

            if ($this->_isUriAllowed($filepath) !== true) {
                unset($this->_files[$filepath]);
                $unsets[] = $filepath;
            }
        }

        if (count($unsetExtensions) > 0) {
            $message = __('%d files with extensions "%s" were skipped.',
                array_sum($unsetExtensions), implode('", "', array_keys($unsetExtensions)));
            $this->_folder->addMessage($message);
            _log('[ArchiveFolder] '. __('Folder #%d [%s]: %s',
                $this->_folder->id, $this->_folder->uri, $message));
        }

        if (count($unsets) > 0) {
            $message = __('At least %d forbidden files "%s" were skipped.',
                count($unsets), implode('", "', $unsets));
            $this->_folder->addMessage($message);
            _log('[ArchiveFolder] '. __('Folder #%d [%s]: %s',
                $this->_folder->id, $this->_folder->uri, $message));
        }
    }

    /**
     * List unique documents, according to the type of mapping or the location
     * if there are no mapping file.
     */
    protected function _listDocuments()
    {
        $documents = &$this->_documents;

        // Check which files are metadata files.
        $documents = array();
        // Keep order of current files, so process by file.
        foreach ($this->_files as $filepath => $filename) {
            foreach ($this->_mappings as $name => $mapping) {
                if ($mapping->isMetadataFile($filepath)) {
                    // Save the path to the metadata file.
                    $this->_metadataFiles[$filepath] = $filename;
                    // And remove it from the list of files.
                    unset($this->_files[$filepath]);
                    try {
                        $metadataDocs = $mapping->listDocuments($filepath);
                    } catch (ArchiveFolder_BuilderException $e) {
                        throw new ArchiveFolder_BuilderException($e->getMessage());
                    } catch (Exception $e) {
                        throw new ArchiveFolder_BuilderException(__('The document "%s" has an issue: %s', $filepath, $e->getMessage()));
                    }
                    $documents = array_merge($documents, $metadataDocs);
                }
            }
        }

        // Add files as items if they are not referenced.

        // List files that are referenced.
        $referencedFiles = array();
        foreach ($documents as &$document) {
            if (!empty($document['files'])) {
                foreach ($document['files'] as $file) {
                    $referencedFiles[$file['path']] = $file['path'];
                }
            }
        }

        // Get the list of not referenced files.
        $remainingFiles = array_diff_key($this->_files, $referencedFiles);

        $metadataDocs = $this->_listRemainingDocuments($remainingFiles);
        $documents = array_merge($documents, $metadataDocs);

        // Reset order or documents and starts from 1.
        $documents = array_values($documents);
        array_unshift($documents, '');
        unset($documents[0]);

        // Reset order of files and starts from 1.
        foreach ($documents as &$document) {
            // A last check in order to always set "files".
            if (empty($document['files'])) {
                $document['files'] = array();
            }
            else {
                $document['files'] = array_values($document['files']);
                array_unshift($document['files'], '');
                unset($document['files'][0]);
            }
        }
    }

    /**
     * List documents that are not referenced by any metadata files.
     *
     * @param array $remainingFiles Associative array of filepaths and names.
     * @return array List of documents.
     */
    protected function _listRemainingDocuments($remainingFiles)
    {
        $documents = array();

        // Prepare the documents without metadata.
        switch ($this->_getParameter('unreferenced_files')) {
            case 'by_file':
                $startRelative = strlen($this->_folder->uri);
                foreach ($remainingFiles as $filepath => $filename) {
                    $relativeFilepath = trim(substr($filepath, $startRelative), '/');

                    $doc = array();
                    // The name is different from the file one to get a
                    // different default identifier.
                    $dir = pathinfo($relativeFilepath, PATHINFO_DIRNAME) == '.'
                        ? ''
                        : pathinfo($relativeFilepath, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR;
                    $doc['name'] = $dir . pathinfo($relativeFilepath, PATHINFO_FILENAME);

                    $file = array();
                    $file['path'] = $filepath;
                    $file['name'] = $relativeFilepath;
                    $doc['files'][] = $file;

                    $documents[] = $doc;
                }
                break;

            case 'by_directory':
                $startRelative = strlen($this->_folder->uri);
                foreach ($this->_folders as $folderpath => $foldername) {
                    $relativeFolderpath = trim(substr($folderpath, $startRelative), '/');
                    $folderpathClean = rtrim($folderpath, '/');

                    $doc = array();
                    $doc['name'] = $relativeFolderpath;

                    foreach ($remainingFiles as $filepath => $filename) {
                        // Check if the file is in the folder (not subfolder).
                        $dirname = rtrim(substr($filepath, 0, -strlen($filename)), '/');
                        if ($dirname == $folderpathClean) {
                            $relativeFilepath = trim(substr($filepath, $startRelative), '/');

                            $file = array();
                            $file['path'] = $filepath;
                            $file['name'] = $relativeFilepath;
                            $doc['files'][] = $file;
                            unset($remainingFiles[$filepath]);
                        }
                    }

                    // Don't add empty folders.
                    if (!empty($doc['files'])) {
                        $documents[] = $doc;
                    }
                }
                if (!empty($remainingFiles)) {
                   throw new ArchiveFolder_BuilderException(__('Some files are not manageable "%s".', implode('", "', $remainingFiles)));
                }
                break;

            case 'skip':
            default:
                break;
        }

        return $documents;
    }

    /**
     * Remove documents with files with a forbidden extension or outside of the
     * allowed path.
     */
    protected function _checkDocuments()
    {
        $documents = &$this->_documents;

        $unsets = array();
        foreach ($documents as $key => $document) {
            foreach ($document['files'] as $order => $file) {
                if (!$this->_isExtensionAllowed($file['path'])) {
                    unset($documents[$key]);
                    $unsets[] = $document['name'];
                    break;
                }

                if ($this->_isUriAllowed($file['path']) !== true) {
                    unset($documents[$key]);
                    $unsets[] = $document['name'];
                    break;
                }
            }
        }

        if (count($unsets) > 0) {
            if (count($unsets) == 1) {
                $message = __('%d document with forbidden files (extension or uri) was skipped: %s.',
                    count($unsets), '"' . implode('", "', $unsets) . '"');
            }
            else {
                $message = __('%d documents with forbidden files (extension or uri) were skipped: %s.',
                    count($unsets), '"' . implode('", "', $unsets) . '"');
            }
            $this->_folder->addMessage($message);
            _log('[ArchiveFolder] '. __('Folder #%d [%s]: %s',
                $this->_folder->id, $this->_folder->uri, $message));
        }
    }

    /**
     * Create OAI identifiers for each document and file.
     */
    protected function _createOaiIdentifiers()
    {
        $listOaiIdentifiers = array();
        foreach ($this->_documents as $orderDocument => &$document) {
            $document['oai_id'] = $this->_oaiIdentifier->create(array(
                array($orderDocument => $document),
            ));
            $listOaiIdentifiers[] = $document['oai_id'];
            // An identifier is prepared even if the file is not a record to
            // simplify process.
            foreach ($document['files'] as $orderFile => &$file) {
                $file['oai_id'] = $this->_oaiIdentifier->create(array(
                    array($orderDocument => $document),
                    array($orderFile => $file),
                ));
                $listOaiIdentifiers[] = $file['oai_id'];
            }
        }

        // Check if all identifiers are unique.
        $unique = array_filter(array_unique($listOaiIdentifiers));
        if (empty($unique) || count($unique) != count($listOaiIdentifiers)) {
           throw new ArchiveFolder_BuilderException(__('Some oai identifiers are not unique. Check names of your documents.'));
        }
    }

    /**
     * Copy local files inside the cache (default: files/repositories).
     *
     * @todo Some files shouldn't be copied: use list documents? Probably no.
     * @todo Copy the hierarchy organized as resulting documents? Probably no.
     */
    protected function _cacheFilesIntoLocalRepository()
    {
        $startRelative = strlen($this->_folder->uri);
        $cacheFolder = $this->_folder->getCacheFolder();

        // First, create all folders.
        foreach ($this->_folders as $folderpath => $foldername) {
            $doc = array();
            $relativeFolderpath = trim(substr($folderpath, $startRelative), '/');
            // The url is raw encoded and should be decoded.
            if ($this->_transferStrategy == 'Url') {
                $relativeFolderpath = rawurldecode($relativeFolderpath);
            }
            $absoluteFolderPath = $cacheFolder . '/' . $relativeFolderpath;
            if (file_exists($absoluteFolderPath)) {
                if (is_file($absoluteFolderPath)) {
                    throw new ArchiveFolder_BuilderException(__('Unable to create the folder "%s": there is a file with the same name.', $relativeFolderpath));
                }
                $result = true;
            }
            else {
                $result = @mkdir($absoluteFolderPath, 0775, true);
            }
            if (!$result) {
                throw new ArchiveFolder_BuilderException(__('Unable to create the cache for folder "%s".', $relativeFolderpath));
            }
        }

        // Second, copy each file.
        foreach ($this->_files as $filepath => $filename) {
            $relativeFilepath = trim(substr($filepath, $startRelative), '/');
            // The url is raw encoded and should be decoded.
            if ($this->_transferStrategy == 'Url') {
                $relativeFilepath = rawurldecode($relativeFilepath);
            }
            $result = @copy($filepath, $cacheFolder . '/' . $relativeFilepath);
            if (!$result) {
                throw new ArchiveFolder_BuilderException(__('Unable to copy the file "%s" in the cache.', $filepath));
            }
        }
    }

    /**
     * Create repository of documents according to the type of the folder.
     *
     * @link http://www.openarchives.org/OAI/2.0/guidelines-static-repository.htm
     *
     * @internal The prefix oai is needed anywhere, because the namespace of the
     * static repository is not the same than the main oai openarchives.
     */
    protected function _createStaticRepository()
    {
        // Create the xml for the static repository.
        $writer = &$this->_writer;
        $documentWriter = &$this->_documentWriter;

        $this->_createTempXmlFile();

        // Set the xml. Xml Writer is used because the repository can be big.
        $writer = new XMLWriter();
        $writer->openUri($this->_xmlpathTemp);
        $writer->setIndent(self::XML_INDENT);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');

        // Prepare the static repository.
        $this->_startStaticRepository();
        $this->_identifyRepository();

        // List the metadata formats of the repository.
        $writer->startElement('ListMetadataFormats');
        foreach ($this->_metadataFormats as $prefix => $format) {
            $writer->startElement('oai:metadataFormat');
            $format->setWriter($writer);
            $format->fillMetadataFormat();
            $writer->endElement();
        }
        $writer->endElement();

        // Metadata may be different when files are separated.
        $recordsForFiles = (boolean) $this->_getParameter('records_for_files');

        if ($this->_folder->hasBeenStopped()) return;

        // Prepare the document writer, that will be emptied for each document.
        $documentWriter = new XMLWriter();
        $documentWriter->openMemory();
        $documentWriter->setIndent(self::XML_INDENT);
        $documentWriter->setIndentString('  ');
        // The document has no xml header.
        // Prepare the writer for each format.
        foreach ($this->_metadataFormats as $prefix => $format) {
            $format->setWriter($documentWriter);
        }

        // For all formats, loop all documents to create the xml records.
        foreach ($this->_metadataFormats as $prefix => $format) {
            $writer->startElement('ListRecords');
            $writer->writeAttribute('metadataPrefix', $format->getMetadataPrefix());

            foreach ($this->_documents as $indexDocument => $document) {
                $this->_fillRecord($document, $prefix, 'Item', $indexDocument);

                if ($recordsForFiles
                        && isset($document['files'])
                        && $format->getParameterFormat('support_separated_files')
                    ) {
                    foreach ($document['files'] as $order => $file) {
                        $this->_fillRecord($file, $prefix, 'File', $order);
                    }
                }

                $documentWriter->flush();
                $writer->flush();

                if ($this->_folder->hasBeenStopped()) return;
            }
            // End ListRecords.
            $writer->endElement();
        }

        // End the static repository.
        $writer->endDocument();
        $writer->flush();
    }

    /**
     * Create the base of the static repository.
     */
    protected function _startStaticRepository()
    {
        $writer = $this->_writer;

        $writer->startElement('Repository');
        $writer->writeAttribute('xmlns', 'http://www.openarchives.org/OAI/2.0/static-repository');
        $writer->writeAttribute('xmlns:oai', 'http://www.openarchives.org/OAI/2.0/');
        $writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $writer->writeAttribute('xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/static-repository http://www.openarchives.org/OAI/2.0/static-repository.xsd');
    }

    /**
     * Create the identification of the repository.
     */
    protected function _identifyRepository()
    {
        $writer = $this->_writer;

        $writer->startElement('Identify');
        $writer->writeElement('oai:repositoryName', $this->_getParameter('repository_name'));
        $writer->writeElement('oai:baseURL', $this->_getParameter('repository_base_url'));
        $writer->writeElement('oai:protocolVersion', '2.0');
        $emails = explode(' ', $this->_getParameter('admin_emails'));
        foreach ($emails as $email) {
            $writer->writeElement('oai:adminEmail', trim($email));
        }
        $writer->writeElement('oai:earliestDatestamp', $this->_getEarliestDatestamp());
        $writer->writeElement('oai:deletedRecord', 'no');
        $writer->writeElement('oai:granularity', 'YYYY-MM-DD');

        // No Oai Identifier, because this is defined by the OaiPmhGateway.

        $writer->endElement();
    }

    /**
     * Fill the metadata of a record.
     *
     * @param array $doc Document or file array.
     * @param string $prefix
     * @param string $recordType "Item" of "File".
     * @param integer $order
     * @return void
     */
    protected function _fillRecord($document, $prefix, $recordType = 'Item', $order = null)
    {
        $writer = $this->_writer;
        $documentWriter = $this->_documentWriter;

        // If there is an xml file for the current document, use it directly for
        // the specified format.
        if (!empty($document['xml']) && !empty($document['format_xml'])
                && $prefix == $document['format_xml']
            ) {
            $documentWriter->writeRaw($document['xml']);
        }
        // Default conversion.
        else {
            $format = $this->_metadataFormats[$prefix];
            if ($recordType == 'Item') {
                $format->fillRecord($document);
            }
            // Record type is a file.
            else {
                $format->fillFileAsRecord($document, $order);
            }
        }

        // Check if the document have been updated.
        $oaiIdentifier = $this->_createFullOaiIdentifier($document['oai_id']);
        $datestamp = $this->_isDocumentUpdated($oaiIdentifier, $prefix);
        $datestamp = $datestamp ?: $this->_getParameter('datestamp');

        // Add the document to the static repository.
        $writer->startElement('oai:record');
        $this->_fillRecordHeader($oaiIdentifier, $datestamp);

        $writer->startElement('oai:metadata');
        // TODO Indent / include the document properly or remove indent.
        $documentXml = $documentWriter->outputMemory(true);
        $writer->writeRaw(PHP_EOL . $documentXml);
        $writer->endElement();

        /**
        // Currently not available.
        if ($recordType == 'Item') {
            $this->_fillProvenance($document);
        }
        */

        // End oai:record.
        $writer->endElement();
    }

    /**
     * Fill the header of a record.
     *
     * @param string $oaiIdentifier The full oai identifier of the document.
     * @param string $datestamp
     */
    protected function _fillRecordHeader($oaiIdentifier, $datestamp)
    {
        $writer = $this->_writer;

        // The header of the record doesn't depend on format.
        $writer->startElement('oai:header');
        $writer->writeElement('oai:identifier', $oaiIdentifier);
        $writer->writeElement('oai:datestamp', $datestamp);
        $writer->endElement();
    }

    /**
     * Fill the provenance of a document.
     *
     * @todo About provenance for harvested records (see http://www.openarchives.org/OAI/2.0/guidelines-provenance.htm)
     *
     * @param array $doc Document or file array.
     */
    protected function _fillProvenance($document)
    {
        return;

        $writer = $this->_writer;
        $writer->startElement('oai:about');
        $writer->startElement('oai:provenance');
        $writer->writeAttribute('xmlns', 'http://www.openarchives.org/OAI/2.0/provenance');
        $writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $writer->writeAttribute('xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/provenance http://www.openarchives.org/OAI/2.0/provenance.xsd');
        $writer->startElement('oai:originDescription');
        // $writer->writeAttribute('harvestDate', 'the time stamp of harvest');
        // $writer->writeAttribute('altered', 'true or false');
        // $writer->writeElement('baseUrl', 'the original base url');
        // $writer->writeElement('identifier', 'the original oai identifier);
        // $writer->writeElement('datestamp', 'the original timestamp);
        // $writer->writeElement('metadataNamespace', 'the original namespace format);
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
    }

    /**
     * Check if a document have been updated, and, if no, return the datestamp.
     *
     * @param string $oaiIdentifier The full oai identifier of the document.
     * @param string $prefix The format to check.
     * @return string|false If no change, return the old datestamp, else false.
     */
    protected function _isDocumentUpdated($oaiIdentifier, $prefix)
    {
        $documentWriter = $this->_documentWriter;

        $existingRecord = $this->_getRecord($oaiIdentifier, $prefix);
        // If not exists, it is new record.
        if (empty($existingRecord)) {
            return false;
        }

        // The prefix may or may not be set.
        $existingDocument = $existingRecord->metadata->children();
        if (empty($existingDocument)) {
            $existingDocument = $existingRecord->metadata->children($prefix, true);
            if (empty($existingDocument)) {
                return false;
            }
        }
        $existingDocument = $existingDocument[0];

        // Warning: some formats, like Mets, keep a time stamp inside content.
        $format = $this->_metadataFormats[$prefix];
        if (!$format->getParameterFormat('compare_directly')) {
            $format->cleanToCompare($existingDocument);
        }

        // To avoid differences of encoded entities and other issues, the
        // documents are cleaned.
        $existingDocument = $this->_normalizeXml($existingDocument);
        $documentXml = $this->_normalizeXml($documentWriter->outputMemory(false));
        if (empty($existingDocument)
                || empty($documentXml)
                || $existingDocument != $documentXml
            ) {
            return false;
        }

        return (string) $existingRecord->header->datestamp;
    }

    /**
     * Get the xml content of a record for the specified prefix.
     *
     * @see OaiPmhGateway_ResponseGenerator::_getRecord()
     *
     * @param string $identifier
     * @param string $prefix
     * @return SimpleXml|null|boolean The record if found, null if not found,
     * false if error (incorrect format). The error is set if any.
     */
    protected function _getRecord($identifier, $metadataPrefix)
    {
        // Prepare the xml reader for the existing static repository.
        // Don't use a static value to allow tests.
        $localRepositoryFilepath = $this->_folder->getLocalRepositoryFilepath();
        if (!file_exists($localRepositoryFilepath)) {
            return  false;
        }

        // Read the xml from the beginning.
        $reader = new XMLReader;
        $result = $reader->open($localRepositoryFilepath, null, LIBXML_NSCLEAN);
        if (!$result) {
            $localRepositoryFilepath = false;
            return false;
        }

        $record = null;

        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT
                    && $reader->name == 'ListRecords'
                    && $reader->getAttribute('metadataPrefix') === $metadataPrefix
                ) {
                // Loop on all records until the one of the identifier (if
                // prefix is not found above, it's bypassed because there is no
                // new element to read.
                while ($reader->read()) {
                    if ($reader->nodeType == XMLReader::ELEMENT
                            && $reader->name === 'oai:record'
                        ) {
                        // Because XMLReader is a stream reader, forward only,
                        // and the identifier is not the first element, it is
                        // saved temporary.
                        $currentRecord = $reader->readOuterXml();
                        $recordXml = @simplexml_load_string($currentRecord, 'SimpleXMLElement', 0, 'oai', true);

                        // Check conditions.
                        if ((string) $recordXml->header->identifier === $identifier) {
                            $record = $recordXml;
                            break 2;
                        }
                        $reader->next();
                    }

                    // Don't continue to list records with another prefix.
                    if ($reader->nodeType == XMLReader::END_ELEMENT
                            && $reader->name == 'ListRecords'
                        ) {
                        break 2;
                    }
                }
            }
        }

        $reader->close();
        return $record;
    }

    /**
     * Normalize an xml (remove indent).
     *
     * @param string $xml
     * @return string Cleaned xml.
     */
    protected function _normalizeXml($xml)
    {
        $dom = new DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        if (is_object($xml)) {
            $xml = $xml->asXml();
        }
        $result = @$dom->loadXML($xml, LIBXML_NSCLEAN);
        if (!$result) {
            return '';
        }
        $xml = $dom->saveXML();

        // TODO Check why the xsi namespace may be set or not at record level.
        $xml = str_replace(' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"', '', $xml);

        return $xml;
    }

    /**
     * Return the full oai identifier of a record.
     *
     * @param string $recordIdentifier
     * @return string Full oai identifier of the record.
     */
    protected function _createFullOaiIdentifier($recordIdentifier)
    {
        // Filter the id (some characters should be escaped: see RFC 3986).
        // rawurlencode() cannot be used, because reserved characters must not
        // be encoded. Nevertheless, this is already an uri. So the issue
        // concerns spaces and non-autoconverted paths principaly.
        $filters = array(
            ' ' => '%20',
        );
        $id = str_replace(array_keys($filters), array_values($filters), $recordIdentifier);
        return 'oai:' . $this->_getParameter('repository_domain') . ':' . $id;
    }

    /**
     * Helper to get the earlieast datestamp of the repository.
     *
     * @todo Currently, return unix timestamp of 0 (1970).
     *
     * @return string OAI-PMH date stamp.
     */
    protected function _getEarliestDatestamp()
    {
        return gmdate('Y-m-d', 0);
    }

    /**
     * Save the static repository.
     *
     * @return void Success in all case, else thrown error.
     */
    protected function _saveStaticRepository()
    {
        // Check and save the static repository (overwrite existing one).
        $xmlpath = $this->_folder->getLocalRepositoryFilepath();
        if (file_exists($this->_xmlpathTemp) && filesize($this->_xmlpathTemp)) {
            copy($this->_xmlpathTemp, $xmlpath);
            unlink($this->_xmlpathTemp);
        }
        else {
            throw new ArchiveFolder_BuilderException(__('The static repository has not been created.'));
        }

        if (!file_exists($xmlpath) || !filesize($xmlpath)) {
            throw new ArchiveFolder_BuilderException(__('The static repository cannot be copied in destination folder.'));
        }
    }

    /**
     * List recursively a directory to get all selected folders and files paths.
     *
     * @param string $path Path of the directory to check.
     *
     * @return associative array of dirpath / dirname and filepath / filename or
     * false if error.
     */
    private function _iterateDirectory($path)
    {
        $dirnames = array();
        $filenames = array();

        $path = realpath($path);
        $directoryIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach ($directoryIterator as $name => $pathObject) {
            // TODO Readable should be check for normal listing too.
            // if ($pathObject->isReadable()) {
            if ($pathObject->isDir()) {
                $dirname = ($name == $path)
                    // The root folder should have a name too.
                    ? './'
                    : substr(rtrim($name, '.'), 0, -1);
                $dirnames[$dirname] = basename($dirname);
            }
            else {
                $filenames[$name] = basename($name);
            }
            // }
        }

        ksort($dirnames);
        ksort($filenames);

        return array(
            'dirnames' => $dirnames,
            'filenames' => $filenames,
        );
    }

    /**
     * List recursively a directory to get all selected folders and files paths.
     *
     * @internal Same as _iterateDirectory(), but without use of iterator() and
     * can process http directory. The extension and the recursive parameters
     * are not used in the class, because all folders and filesneed to be
     * returned.
     *
     * @param string $directory Directory to check.
     * @param string $extension Extension (prefixed with "." if needed).
     * @param boolean $recursive Recursive or not in subfolder.
     *
     * @return associative array of dirpath / dirname and filepath / filename or
     * false if error.
     */
    private function _listDirectory($directory, $extension = '', $recursive = true)
    {
        $dirnames = array();
        $filenames = array();

        // Get directories and files via http or via file system.
        // Get via http/https.
        if (parse_url($directory, PHP_URL_SCHEME) == 'http'
                || parse_url($directory, PHP_URL_SCHEME) == 'https'
            ) {
            $result = $this->_scandirOverHttp($directory, $extension);
            if (empty($result)) {
                return array(
                    'dirnames' => $dirnames,
                    'filenames' => $filenames,
                );
            }
            $dirs = &$result['dirs'];
            $files = &$result['files'];
        }
        // Get via file system.
        else {
            $dirs = glob($directory . '/*', GLOB_ONLYDIR);
            $files = glob($directory . '/*' . $extension, GLOB_MARK);
            // Remove directories because glob() has no flag to get only files.
            foreach ($files as $key => $file) {
                if (substr($file, -1) == '/') {
                    unset($files[$key]);
                }
            }
        }

        // Recursive call to this function for subdirectories.
        if ($recursive == true) {
            foreach ($dirs as $dir) {
                $subdirectory = $this->_listDirectory($dir, $extension, $recursive);
                if ($subdirectory !== false) {
                    $dirnames = array_merge($dirnames, $subdirectory['dirnames']);
                    $filenames = array_merge($filenames, $subdirectory['filenames']);
                }
            }
        }

        // Return dirnames in a formatted array.
        foreach ($dirs as $dir) {
            $dirnames[$dir] = basename($dir);
        }
        ksort($dirnames);

        // Return filenames in a formatted array.
        foreach ($files as $file) {
            $filenames[$file] = basename($file);
        }
        ksort($filenames);

        return array(
            'dirnames' => $dirnames,
            'filenames' => $filenames,
        );
    }

    /**
     * Scan a directory available only via http (web pages).
     *
     * @param string $directory Directory to check.
     * @param string $extension Extension (prefixed with "." if needed).
     *
     * @return associative array of directories and filepaths.
     */
    private function _scandirOverHttp($directory, $extension = '')
    {
        // The @ avoids a warning when the url is not available.
        $page = @file_get_contents($directory);
        if (empty($page)) {
            return false;
        }

        $dirs = array();
        $files = array();

        // Prepare extension for regex.
        $extension = preg_quote($extension);

        // Add a slash to the url in order to append relative filenames easily.
        if (substr($directory, -1) != '/') {
            $directory .= '/';
        }

        // Get parent directory.
        $parent = dirname($directory) . '/';

        // Get the domain if needed.
        $domain = parse_url($directory);
        if (!isset($domain['user'])) {
            $domain['user'] = '';
        }
        if (!isset($domain['pass'])) {
            $domain['pass'] = '';
        }
        $user = ($domain['user'] . ':' . $domain['pass'] != ':') ? $domain['user'] . ':' . $domain['pass'] . '@' : '';
        $port = !empty($domain['port']) ? ':' . $domain['port'] : '';
        $domain = $domain['scheme'] . '://' . $user . $domain['host'] . $port;

        // List all links.
        $matches = array();
        preg_match_all("/(a href\=\")([^\?\"]*)(\")/i", $page, $matches);
        // Remove duplicates.
        $matches = array_combine($matches[2], $matches[2]);

        // Check list of urls.
        foreach ($matches as $match) {
            // Add base url to relative ones.
            $urlScheme = parse_url($match, PHP_URL_SCHEME);
            if ($urlScheme != 'http' && $urlScheme != 'https') {
                // Add only domain to absolute url without domain.
                if (substr($match, 0, 1) == '/') {
                    $match = $domain . $match;
                }
                else {
                    $match = $directory . $match;
                }
            }

            // Remove parent and current directory.
            if ($match == $parent || $match == $directory || ($match . '/') == $parent || ($match . '/') == $directory) {
                // Don't add it.
            }
            // Check if this a directory.
            elseif (substr($match, -1) == '/') {
                $dirs[] = $match;
            }
            elseif (empty($extension)) {
                $files[] = $match;
            }
            // Check the extension.
            elseif (preg_match('/^.+' . $extension . '$/i', $match)) {
                $files[] = $match;
            }
        }

        // Percent encoding is case-insensitive, but rawurlencode() use upper
        // case, so result should be upper-cased, so it will be possible to
        // compare them internally (other characters should not change).
        $dirs = $this->_upperCasePercentEncoding($dirs);
        $files = $this->_upperCasePercentEncoding($files);
        return array(
            'dirs' => $dirs,
            'files' => $files,
        );
    }

    /**
     * Upper-case percent encoding of values of an array.
     *
     * @param array $array
     * @return array Upper-cased percent encoding of values of the array.
     */
    private function _upperCasePercentEncoding($array)
    {
        $result = array();

        foreach ($array as $key => $value) {
            $upperValue = preg_replace_callback(
                '/%[0-9a-f]{2}/',
                array($this, '_upperCaseMatches'),
                $value);
            $result[$key] = $upperValue;
        }

        return $result;
    }

    /**
     * Callback function for _upperCasePercentEncoding().
     *
     * @see _upperCasePercentEncoding()
     */
    private function _upperCaseMatches(array $matches)
    {
        return strtoupper($matches[0]);
    }

    /**
     * Check if extension of a uri is allowed.
     *
     * @param string $uri
     * @return boolean
     */
    protected function _isExtensionAllowed($uri)
    {
        static $whiteList;
        static $blackList;

        // Prepare the white list of extensions.
        if (is_null($whiteList)) {
            $extensions = (string) get_option(Omeka_Validate_File_Extension::WHITELIST_OPTION);
            $whiteList = $this->_prepareListOfExtensions($extensions);
        }

        // Prepare the black list of extensions.
        if (is_null($blackList)) {
            $extensions = (string) $this->_getParameter('exclude_extensions');
            $blackList = $this->_prepareListOfExtensions($extensions);
        }

        $extension = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
        if (!empty($blackList) && in_array($extension, $blackList)) {
            return false;
        }
        elseif (!empty($whiteList) && !in_array($extension,$whiteList)) {
            return false;
        }

        return true;
    }

    /**
     * Convert and clean a list of extensions from a string into an array.
     *
     * @param string $extensions List of extensions, separated with a space or a
     * comma.
     * @return array Cleaned array of extensions.
     */
    protected function _prepareListOfExtensions($extensions)
    {
        $extensions = str_replace(',', ' ', $extensions);
        $extensions = explode(' ', $extensions);
        $extensions = array_map('trim', $extensions);
        $extensions = array_map('strtolower', $extensions);
        $extensions = array_unique($extensions);
        $extensions = array_filter($extensions);
        return $extensions;
    }

    /**
     * Check if a url is allowed.
     *
     * @param string $uri The uri to check.
     * @return boolean|string True if allowed, else a string explains why.
     */
    protected function _isUriAllowed($uri)
    {
        $scheme = parse_url($uri, PHP_URL_SCHEME);
        // The check is done via the server for external urls.
        if (in_array($scheme, array('http', 'https', 'ftp', 'sftp'))) {
            return true;
        }

        // Check a local path.
        if ($scheme == 'file' || $uri[0] == '/') {
            // Check the security setting.
            $settings = Zend_Registry::get('archive_folder');
            if ($settings->local_folders->allow != '1') {
                return __('Local paths are not allowed by the administrator.');
            }

            // Check the base path.
            $basepath = $settings->local_folders->base_path;
            $realpath = realpath($basepath);
            if ($basepath !== $realpath || strlen($realpath) <= 2) {
                return __('The base path is not correct.');
            }

            // Check the uri.
            if ($settings->local_folders->check_realpath == '1') {
                if (strpos(realpath($uri), $realpath) !== 0
                        || !in_array(substr($uri, strlen($realpath), 1), array('', '/'))
                    ) {
                    return __('The uri "%s" is not allowed.', $uri);
                }
            }

            // The uri is allowed.
            return true;
        }

        // Unknown or unmanaged scheme.
        return __('The uri "%" is not correct.', $uri);
    }

    /**
     * Check if a uri is available or readable according to transfer strategy.
     *
     * @param string $uri
     * @return boolean
     */
    protected function _isUriAvailable($uri)
    {
        if (empty($uri)) {
            return false;
        }

        switch ($this->_transferStrategy) {
            case 'Url':
                return $this->_isRemoteUrlAvailable($uri);
            case 'Filesystem':
                return Zend_Loader::isReadable($uri);
        }

        return false;
    }

    /**
     * Check if an url is available.
     *
     * @param string $url
     * @return boolean
     */
    private function _isRemoteUrlAvailable($url)
    {
        $resURL = curl_init();
        curl_setopt($resURL, CURLOPT_URL, $url);
        curl_setopt($resURL, CURLOPT_BINARYTRANSFER, 1);
        curl_setopt($resURL, CURLOPT_FAILONERROR, 1);
        curl_exec($resURL);
        $result = curl_getinfo($resURL, CURLINFO_HTTP_CODE);
        curl_close($resURL);
        return $result == 200 || $result == 301 || $result == 302 || $result == 304;
    }

    /**
     * Return the full path to a temporary xml file for the current repository.
     *
     * @return string Absolute path to an empty file.
     */
    private function _createTempXmlFile()
    {
        $xmlpath = $this->_folder->getLocalRepositoryFilepath();
        $extension = '.' . pathinfo($xmlpath, PATHINFO_EXTENSION);
        $path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . pathinfo($xmlpath, PATHINFO_FILENAME)
            . '_' . date('Ymd-His')
            . $extension;
        $i = 0;
        $testpath = $path;
        while (file_exists($testpath)) {
            $testpath = substr($path, 0, -strlen($extension)) . '_' . ++$i . $extension;
        }

        touch($testpath);
        $this->_xmlpathTemp = $testpath;
        return $this->_xmlpathTemp;
    }
}
