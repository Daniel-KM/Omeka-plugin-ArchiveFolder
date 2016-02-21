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

    // The mappings classes.
    protected $_mappings;

    // The format class for Archive Document.
    protected $_format;

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

    /**
     * List of documents, according to parameters:
     * array List of documents (items)
     *     index => Index is used as order; starts from 1 internally when ready
     *         process => internal data used for the process
     *             record type => Item
     *             action
     *             name => relative path (sub-directory) or name (metadata file)
     *             oai_id => oai id of the item (set internally)
     *             format_xml => if any, format of xml metadata to include
     *             xml => if any, the content to include directly for the format
     *         specific => specific data for this record type
     *             collection
     *             item type
     *             tags
     *             public
     *             featured
     *         metadata => array of elements (Dublin Core...)
     *         extra => array of other data, like geolocation
     *         files => ordered array of files attached to the document if any
     *             index => array Index is used as order and starts from 1
     *                 process => internal data used for the process
     *                     record type => File
     *                     action
     *                     name => filepath relative to main folder, else url
     *                     oai_id => oai id of the file (set internally)
     *                     format_xml => format of xml metadata to include
     *                     xml => if any, the content to include for the format
     *                 specific = if any, specific data for this record type
     *                     path => the original filepath (local or http)
     *                     fullpath => the absolute url determined from the path
     *                     original filename
     *                     authentication
     *                 metadata => array of elements (Dublin Core...)
     *                 extra => array of other data
     * In metadata files, the file path may be relative. The mapping class may
     * be used to convert it to absolute path and to relative path name. The
     * index order and the oai_id are set internally.
     * Unrecognized key/values are saved in the "extra" array. They will be
     * included in the static repository only if a format or a hook manage them.
     * By default none are managed, even "tags" and "item type", that are not
     * standard metadata: tags may be replaced by Dublin Core : Subject or
     * Coverage and item types by Dublin Core : Type.
     */
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
     * @param ArchiveFolder_Folder $folder The folder to process.
     * @param string $type The type of process.
     * @return List of documents.
     */
    public function process(ArchiveFolder_Folder $folder, $type = self::TYPE_CHECK)
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
            $this->_checkMappings();
        } catch (ArchiveFolder_BuilderException $e) {
            throw new ArchiveFolder_BuilderException($e->getMessage());
        } catch (Exception $e) {
            throw new ArchiveFolder_BuilderException(__('Mappings cannot be checked: %s', $e->getMessage()));
        }

        $this->_format = new ArchiveFolder_Format_Document($this->_folder->uri, $this->_parameters);

        if ($this->_folder->hasBeenStopped()) return;

        try {
            $this->_listFolderAndFiles();
        } catch (Exception $e) {
            throw new ArchiveFolder_BuilderException(__('Files cannot be checked: %s', $e->getMessage()));
        }

        if (empty($this->_folders) || empty($this->_files)) {
            throw new ArchiveFolder_BuilderException(__('No folder and no file found after listing files.')
                . ' ' . __('Check your rights and your configuration.'));
        }

        if ($this->_folder->hasBeenStopped()) return;

        try {
            $this->_checkFiles();
        } catch (Exception $e) {
            throw new ArchiveFolder_BuilderException(__('Some extensions of paths cannot be checked: %s', $e->getMessage()));
        }

        if (empty($this->_folders) || empty($this->_files)) {
            throw new ArchiveFolder_BuilderException(__('No folder and no file found after checking files.')
                . ' ' . __('Check rights, allowed paths, extensions and configuration.'));
        }

        if ($this->_folder->hasBeenStopped()) return;

        try {
            $this->_listDocuments();
        } catch (ArchiveFolder_BuilderException $e) {
            throw new ArchiveFolder_BuilderException($e->getMessage());
        } catch (ArchiveFolder_Exception $e) {
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
            throw new ArchiveFolder_BuilderException(__('No folder and no file found after checking documents.')
                . ' ' . __('Check rights, allowed paths, extensions, configuration, the metadata in your files or the xsl processing.'));
        }

        if ($this->_folder->hasBeenStopped()) return;

        switch ($type) {
            case self::TYPE_CHECK :
                break;
            case self::TYPE_UPDATE :
                if (empty($this->_folder->identifier)) {
                    throw new ArchiveFolder_BuilderException(__('The repository identifier is not defined.'));
                }

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
                $unsetExtensions[] = pathinfo($file, PATHINFO_BASENAME);
                continue;
            }

            if ($this->_isUriAllowed($filepath) !== true) {
                unset($this->_files[$filepath]);
                $unsets[] = $filepath;
            }
        }

        if (count($unsetExtensions) > 0) {
            $message = __('%d files were skipped because of a forbidden extension: "%s".',
                count($unsetExtensions), implode('", "', $unsetExtensions));
            $this->_folder->addMessage($message);
            _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s',
                $this->_folder->id, $this->_folder->uri, $message));
        }

        if (count($unsets) > 0) {
            $message = __('%d forbidden files were skipped: "%s".',
                count($unsets), implode('", "', $unsets));
            $this->_folder->addMessage($message);
            _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s',
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
                    $referencedFiles[$file['process']['fullpath']] = $file['specific']['path'];
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
                    $doc['process']['record type'] = 'Item';
                    // The name is different from the file one to get a
                    // different default identifier.
                    $dir = pathinfo($relativeFilepath, PATHINFO_DIRNAME) == '.'
                        ? ''
                        : pathinfo($relativeFilepath, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR;
                    $doc['process']['name'] = $dir . pathinfo($relativeFilepath, PATHINFO_FILENAME);
                    $doc['specific'] = array();
                    $doc['metadata'] = array();
                    $doc['extra'] = array();

                    $file = array();
                    $file['process']['record type'] = 'File';
                    $file['process']['name'] = $relativeFilepath;
                    $file['process']['fullpath'] = $filepath;
                    $file['specific']['path'] = $filepath;
                    $file['metadata'] = array();
                    $file['extra'] = array();
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
                    $doc['process']['record type'] = 'Item';
                    $doc['process']['name'] = $relativeFolderpath;
                    $doc['specific'] = array();
                    $doc['metadata'] = array();
                    $doc['extra'] = array();

                    foreach ($remainingFiles as $filepath => $filename) {
                        // Check if the file is in the folder (not subfolder).
                        $dirname = rtrim(substr($filepath, 0, -strlen($filename)), '/');
                        if ($dirname == $folderpathClean) {
                            $relativeFilepath = trim(substr($filepath, $startRelative), '/');

                            $file = array();
                            $file['process']['record type'] = 'File';
                            $file['process']['name'] = $relativeFilepath;
                            $file['process']['fullpath'] = $filepath;
                            $file['specific']['path'] = $filepath;
                            $file['metadata'] = array();
                            $file['extra'] = array();
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

        $unsetExtensions = array();
        $unsets = array();
        foreach ($documents as $key => $document) {
            foreach ($document['files'] as $order => $file) {
                if (!$this->_isExtensionAllowed($file['specific']['path'])) {
                    unset($documents[$key]);
                    $unsetExtensions[] = pathinfo($document['process']['name'], PATHINFO_BASENAME);
                    break;
                }

                if ($this->_isUriAllowed($file['specific']['path']) !== true) {
                    unset($documents[$key]);
                    $unsets[] = $document['process']['name'];
                    break;
                }
            }
        }

        if (count($unsetExtensions) > 0) {
            $message = __('%d documents were skipped because of a forbidden extension: "%s".',
                count($unsetExtensions), implode('", "', $unsetExtensions));
            $this->_folder->addMessage($message);
            _log('[ArchiveFolder] ' . __('Folder #%d [%s]: %s',
                $this->_folder->id, $this->_folder->uri, $message));
        }

        if (count($unsets) > 0) {
            $message = __('%d forbidden documents were skipped: "%s".',
                count($unsets), '"' . implode('", "', $unsets) . '"');
            $this->_folder->addMessage($message);
            _log('[ArchiveFolder] ' . __('Folder #%d [%s]: "%s"',
                $this->_folder->id, $this->_folder->uri, $message));
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

        // Metadata may be different when files are separated.
        $recordsForFiles = (boolean) $this->_getParameter('records_for_files');

        // Set the xml. Xml Writer is used because the repository can be big.
        $writer = new XMLWriter();
        $writer->openUri($this->_xmlpathTemp);
        $writer->setIndent(self::XML_INDENT);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');

        // Prepare the static repository.
        $this->_format->setWriter($writer);
        $this->_startStaticRepository();

        // Prepare the document writer, that will be emptied for each document.
        $documentWriter = new XMLWriter();
        $documentWriter->openMemory();
        $documentWriter->setIndent(self::XML_INDENT);
        $documentWriter->setIndentString('  ');
        // The document has no xml header.

        // The prefix of the format is kept for future updates or it will be
        // removed later.
        $prefix = 'doc';

        // Prepare the writer for each format (only one currently).
        $this->_format->setWriter($documentWriter);
        foreach ($this->_documents as $indexDocument => $document) {
            // The record and associated files are filled in one time.
            $this->_fillRecord($document, $prefix, $indexDocument);

            // Useless with the format document.
            /*
            if ($recordsForFiles
                    && isset($document['files'])
                    && $format->getParameterFormat('support_separated_files')
                ) {
                foreach ($document['files'] as $order => $file) {
                    $this->_fillRecord($file, $prefix, $order);
                }
            }
            */

            $documentWriter->flush();
            $writer->flush();

            if ($this->_folder->hasBeenStopped()) return;
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

        $format = $this->_format;
        $format->startRoot();
    }

    /**
     * Fill the metadata of a record.
     *
     * @param array $doc Document or file array.
     * @param string $prefix
     * @param integer $order
     * @return void
     */
    protected function _fillRecord($document, $prefix, $order = null)
    {
        $writer = $this->_writer;
        $documentWriter = $this->_documentWriter;

        // If there is an xml file for the current document, use it directly for
        // the specified format.
        if (!empty($document['process']['xml']) && !empty($document['process']['format_xml'])
                && $prefix == $document['process']['format_xml']
            ) {
            $documentWriter->writeRaw($document['process']['xml']);
        }
        // Default conversion.
        else {
            $format = $this->_format;
            $recordType = empty($document['process']['record type']) ? '' : $document['process']['record type'];
            switch ($recordType) {
                case 'File':
                    $format->fillFileAsRecord($document, $order);
                    break;
                case 'Item':
                default:
                    $format->fillRecord($document);
                    break;
            }
        }

        // TODO Indent / include the document properly or remove indent.
        $documentXml = $documentWriter->outputMemory(true);
        $writer->writeRaw(PHP_EOL . $documentXml);
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
     * Check if extension (single or complex) of a uri is allowed.
     *
     * @param string $uri
     * @return boolean
     */
    protected function _isExtensionAllowed($uri)
    {
        static $whiteList;
        static $blackList;
        static $longBlackListPattern;

        // Prepare the white, black and long list of extensions.
        if (is_null($whiteList)) {
            $extensions = (string) get_option(Omeka_Validate_File_Extension::WHITELIST_OPTION);
            $whiteList = $this->_prepareListOfExtensions($extensions);

            $extensions = (string) $this->_getParameter('exclude_extensions');
            $blackList = $this->_prepareListOfExtensions($extensions);
            $longBlackList = array_filter($blackList, function($v) {
                return strpos($v, '.') !== false;
            });
            $blackList = array_diff($blackList, $longBlackList);
            if ($longBlackList) {
                $longBlackListPattern = '#('
                    . implode(
                        '|',
                        array_map(
                            function ($v) { return preg_quote($v, '#'); },
                            $longBlackList))
                    . ')$#i';
            }
        }

        $extension = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
        if (!empty($blackList) && in_array($extension, $blackList)) {
            return false;
        }
        elseif (!empty($longBlackListPattern) && preg_match($longBlackListPattern, $uri)) {
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
