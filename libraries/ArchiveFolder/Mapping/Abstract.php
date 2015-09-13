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

    // Internal values.
    // These headers have special meanings in Omeka or in the fork of CsvImport.
    protected $_specialHeaders = array(
        // Name or index of the document, even for attached files with metadata.
        'name' => 'name',
        'item' => 'name',
        'document' => 'name',
        // Attached files.
        'file' => 'files',
        'files' => 'files',
        // 'fileurl' => 'files', // deprecated
        'record type' => 'record type',
        // 'recordtype' => 'record type', // deprecated

        // These ones are used only if a format manages them.
        'item type' => 'item type',
        // 'itemtype' => 'item type', // deprecated
        /*
        // Tags, collection, featured, public, are useless, because there are
        // automatically managed as extra metadata.
        'tags' => 'tags',
        'collection' => 'collection',
        'featured' => 'featured',
        'public => 'public',

        // These ones allow to use same files than the fork of Csv Import.
        'sourceitemid' => 'name', // deprecated
        'updatemode' => 'action', // deprecated
        'updateidentifier' => 'name', // deprecated
        'record identifier' => 'name', // deprecated
        'recordidentifier' => 'name', // deprecated
        // Identifier cannot be used, because it can be a Dublin Core element.
        // 'identifier' => 'name', // not managed
        'action' => 'action', // not managed
        'identifierfield' => 'identifier field', // not managed
        */
    );

    // Element separator is used for the name of the element for some formats.
    protected $_elementNameSeparator = ':';
    protected $_endOfLine = PHP_EOL;

    /**
     * Constructor of the class./
     *
     * @param string $uri The uri of the folder.
     * @param array $parameters The parameters to use for the mapping.
     * @return void
     */
    public function __construct($uri, $parameters)
    {
        $this->_uri = $uri;
        $this->_parameters = $parameters;

        $this->_elementNameSeparator = $this->_getParameter('element_name_separator') ?: ':';

        if ($this->_getParameter('use_qdc')) {
            // Prepare labels of dc terms.
            require PLUGIN_DIR
                . DIRECTORY_SEPARATOR . 'ArchiveFolderDocument'
                . DIRECTORY_SEPARATOR . 'libraries'
                . DIRECTORY_SEPARATOR . 'elements_qdc.php';
            $this->_dcTerms = array();
            foreach ($elements as $element) {
                // Checks are done on lower case names and labels.
                $this->_dcTerms[strtolower($element['name'])] = $element['label'];
                $this->_dcTerms[strtolower($element['label'])] = $element['label'];
            }
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
        $this->_metadataFilepath = $filepath;

        foreach ($this->_checkMetadataFile as $check) {
            switch ($check) {
                case 'false':
                    return false;

                default:
                    $method = '_check' . ucfirst(Inflector::camelize($check));
                    if (method_exists($this, $method) && !$this->$method()) {
                        return false;
                    }
                    break;
            }
        }

        // All tests are ok, or there is no test.
        return true;
    }

    /**
     * Check if the current file is a metadata one.
     *
     * @param string $filepath The current one is used if null.
     * @param string $extension The current one is used if null.
     * @return boolean
     */
    protected function _checkExtension($filepath = null, $extension = null)
    {
        if (is_null($filepath)) {
            $filepath = $this->_metadataFilepath;
        }
        if (is_null($extension)) {
            $extension = $this->_extension;
        }
        return strtolower(pathinfo($filepath, PATHINFO_EXTENSION)) === $extension;
    }

    /**
     * Check if the current file is a metadata one for a double extension.
     *
     * @param string $filepath The current one is used if null.
     * @param string $extension The current one is used if null.
     * @return boolean
     */
    protected function _checkDoubleExtension($filepath = null, $extension = null)
    {
        if (is_null($filepath)) {
            $filepath = $this->_metadataFilepath;
        }
        if (is_null($extension)) {
            $extension = $this->_extension;
        }
        $extension = '.' . $extension;
        return substr(strtolower($filepath), strlen($filepath) - strlen($extension)) === $extension;
    }

    /**
     * Check if the current file is a xml metadata one, without validation.
     *
     * @param string $filepath The current one is used if null.
     * @param string $xmlRoot The current one is used if null.
     * @return boolean
     */
    protected function _checkXml($filepath = null, $xmlRoot = null)
    {
        if (is_null($filepath)) {
            $filepath = $this->_metadataFilepath;
        }
        if (is_null($xmlRoot)) {
            $xmlRoot = $this->_xmlRoot;
        }
        // XmlReader is the quickest and the simplest for such a check, locaaly
        // or remotely.
        $reader = new XMLReader;
        $result = $reader->open($filepath, null, LIBXML_NSCLEAN);
        if ($result) {
            $result = false;
            while ($reader->read()) {
                if ($reader->name != '#comment') {
                    $result = $reader->name === $xmlRoot;
                    break;
                }
            }
        }
        $reader->close();
        return $result;
    }

    /**
     * Check if the current file is a xml metadata one.
     *
     * @param string $filepath The current one is used if null.
     * @param string $xmlRoot The current one is used if null.
     * @param string $xmlNamespace The current one is used if null.
     * @return boolean
     */
    protected function _checkValidateXml($filepath = null, $xmlRoot = null, $xmlNamespace = null)
    {
        if (is_null($filepath)) {
            $filepath = $this->_metadataFilepath;
        }
        if (is_null($xmlRoot)) {
            $xmlRoot = $this->_xmlRoot;
        }
        if (is_null($xmlNamespace)) {
            $xmlNamespace = $this->_xmlNamespace;
        }
        // XmlReader is the quickest and the simplest for such a check, locaaly
        // or remotely.
        $reader = new XMLReader;
        $result = $reader->open($filepath, null, LIBXML_NSCLEAN);
        if ($result) {
            $result = false;
            while ($reader->read()) {
                if ($reader->name != '#comment') {
                    $result = $reader->name === $xmlRoot
                        &&  $reader->getAttribute('xmlns') === $xmlNamespace;
                    break;
                }
            }
        }
        $reader->close();
        return $result;
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
            $this->_prepareDocuments();
            $this->_setXmlFormat();
            $this->_validateDocuments();
            $this->_removeDuplicateMetadata();
        }
        return $this->_processedFiles[$filepath];
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
     * Validate documents, secure paths of files and make them absolute.
     *
     * @internal Only local filepaths are checked.
     */
    protected function _validateDocuments()
    {
        $documents = &$this->_processedFiles[$this->_metadataFilepath];

        // Check file paths and names (if one is absent, the other is used).
        $nameBase = $this->_getRelativePathToFolder($this->_metadataFilepath);
        foreach ($documents as $key => &$document) {
            // Check if the document is empty.
            if (empty($document['metadata']) && empty($document['files']) && empty($document['extra'])) {
                unset($documents[$key]);
                continue;
            }

            // Add an internal name if needed.
            // Warning: this should not be the same than the one defined inside
            // a metadata file, even if the issue is very rare. Nevertheless, it
            // should be enough stable to be updatable in main normal cases.
            if (empty($document['name'])) {
                $document['name'] = $nameBase . ':0' . ($key + 1);
            }

            // Remove a possible null value.
            if (empty($document['files'])) {
                $document['files'] = array();
            }

            foreach ($document['files'] as $order => &$file) {
                // The absolute and the relative paths should be the same file.
                $path = isset($file['path']) && strlen($file['path']) > 0
                    ? $file['path']
                    : (isset($file['name']) ? $file['name'] : null);

                // Check if there is a filepath.
                // Empty() is not used, because "0" can be a content.
                $path = trim($path);
                if (strlen($path) == 0) {
                    throw new ArchiveFolder_BuilderException(__('The filepath for document "%s" is empty.', $document['name']));
                }

                // The path is absolute or relative to the path of the
                // metadata file.
                $absoluteFilePath = $this->_getAbsolutePath($path);
                if (empty($absoluteFilePath)) {
                    throw new ArchiveFolder_BuilderException(__('The file "%s" is incorrect.', $path));
                }

                // No relative path if the file is external to the folder.
                $relativeFilepath = $this->_isInsideFolder($absoluteFilePath)
                    ? $this->_getRelativePathToFolder($path)
                    : $absoluteFilePath;
                if (empty($relativeFilepath)) {
                    throw new ArchiveFolder_BuilderException(__('The file path "%s" is incorrect.', $path));
                }

                $file['path'] = $absoluteFilePath;
                $file['name'] = $relativeFilepath;
            }
        }

        return $documents;
    }

    /**
     * Get the data or element set  and element name from a string.
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
            if (isset($this->_specialHeaders[$lowerName])) {
                return $this->_specialHeaders[$lowerName];
            }

            if (isset($this->_dcTerms[$lowerName])) {
                $elementSetName = 'Dublin Core';
                $elementName = $this->_dcTerms[$lowerName];
            }
            // Empty element set name.
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
     * Returns the relative path for the current metadata file.
     *
     * @todo Check under Windows.
     *
     * @return string The relative path can be empty for root, else it is followed
     * by the directory separator "/" (or "\" for local path under Windows).
     */
    protected function _getRelativeMetadataPath()
    {
        // This static save all relative metadata path by uri and by path.
        // The sub-array for uri is needed for the tests.
        // The metadataFilePath should be absolute to avoid duplicates.
        static $relativeMetadataPaths = array();

        if (!isset($relativeMetadataPaths[$this->_uri][$this->_metadataFilepath])) {
            $relativeMetadataPath = pathinfo(trim(substr($this->_metadataFilepath, 1 + strlen($this->_uri))), PATHINFO_DIRNAME);
            if ($relativeMetadataPath == '.' || $relativeMetadataPath == '') {
                $relativeMetadataPaths[$this->_uri][$this->_metadataFilepath] = '';
            }
            // Not root, so add the separator.
            else {
                // Probably needed for Windows compatibility.
                $separator = $this->_getParameter('transfer_strategy') != 'Filesystem'
                    ? '/'
                    : DIRECTORY_SEPARATOR;

                $relativeMetadataPaths[$this->_uri][$this->_metadataFilepath] = $relativeMetadataPath . $separator;
            }
        }

        return $relativeMetadataPaths[$this->_uri][$this->_metadataFilepath];
    }

    /**
     * Return absolute path to the resource.
     *
     * A security check is done for a local path, that should belong to folder.
     *
     * @param string $path The path to check.
     * @return string|null Absolute path to the resource. Null if error.
     */
    protected function _getAbsolutePath($path)
    {
        // Check if this is an absolute url.
        if ($this->_isRemote($path)) {
            return $path;
        }

        // Check if this is an absolute path.
        if (strpos($path, '/') === 0) {
            $absolutePath = $path;
        }
        // This is a relative path.
        else {
            $relativeMetadataPath = $this->_getRelativeMetadataPath();
            $relativeFilepath = $relativeMetadataPath . $path;
            $absolutePath = $this->_uri . DIRECTORY_SEPARATOR . $relativeFilepath;
        }

        // Set and check real path for local paths (security).
        if ($this->_getParameter('transfer_strategy') == 'Filesystem'
                && (realpath($absolutePath) != $absolutePath
                    || strpos($absolutePath, $this->_uri) !== 0
                    || substr($absolutePath, strlen($this->_uri), 1) !=  '/'
                )
            ) {
            $absolutePath = null;
        }

        return $absolutePath;
    }

    /**
     * Return path relative to the current folder if it is relative.
     *
     * If they are relative, the paths of the files are relative to the file
     * metadata. This method sets it relative to folder.
     * A security check is done for a local path.
     *
     * @param string $path
     * @return string|null Path relative to folder. Null if error.
     */
    protected function _getRelativePathToFolder($path)
    {
        // Check if this is an absolute url.
        if ($this->_isRemote($path)) {
            // Check if this url is not inside the folder.
            if (strpos($path, $this->_uri) !== false) {
                return $path;
            }

            $relativeFilepath = trim(substr($path, 1 + strlen($this->_uri)));
            $absolutePath = $path;
        }
        // Check if this is an absolute path.
        elseif (strpos($path, '/') === 0) {
            $relativeFilepath = trim(substr($path, 1 + strlen($this->_uri)));
            $absolutePath = $path;
        }
        // This is a relative path.
        else {
            $relativeMetadataPath = $this->_getRelativeMetadataPath();
            $relativeFilepath = $relativeMetadataPath . $path;
            $absolutePath = $this->_uri . DIRECTORY_SEPARATOR . $relativeFilepath;
        }

        // Set and check real path for local paths (security).
        if (strpos($absolutePath, '/') === 0) {
            // Here, local paths may be raw url encoded.
            $absolutePath = rawurldecode($absolutePath);
            // Check if the path is in the folder.
            if (realpath($absolutePath) != $absolutePath
                    || strpos($absolutePath, $this->_uri) !== 0
                ) {
                $relativeFilepath = '';
            }
        }
        return $relativeFilepath;
    }

    /**
     * Get url inside the repository folder from a path inside a metadata file.
     *
     * @param string $path File path, local or remote, absolute or relative.
     * @param boolean $absolute If true, returns an absolute url (always if
     * external urls).
     * @param boolean $urlencode If true, URL-encode according to RFC 3986.
     * This parameter is not used for external urls, that should be already
     * formatted.
     * @return string|null Absolute url to the resource inside repository, or
     * original url for external resource. Null if error.
     */
    protected function _getRepositoryUrlForFile($path, $absolute = true, $urlencode = true)
    {
        // No process for path outside folder (check is done somewhere else).
        $absolutePath = $this->_getAbsolutePath($path);
        if (!$this->_isInsideFolder($absolutePath)) {
            // An absolute path should be already urlencoded. An external local
            // path will be removed later.
            return $absolutePath;
        }

        $relativeFilepath = $this->_getRelativePathToFolder($path);
        if (empty($relativeFilepath)) {
            throw new ArchiveFolder_BuilderException(__('The file path "%s" is not correct.', $path));
        }

        // Check if this is an external url.
        if ($this->_isRemote($relativeFilepath)) {
            return $path;
        }

        if ($urlencode) {
            $path = $this->_rawurlencodeRelativePath($relativeFilepath);
            return $absolute
                ? $this->_getParameter('repository_folder') . $path
                : $path;
        }

        // A normal relative path.
        $path = str_replace(array('#', '?'), array('%23', '%3F'), $relativeFilepath);
        return $absolute
            ? $this->_getParameter('repository_folder_human') . $path
            : $path;
    }

    /**
     * Remove duplicate metadata that can be found in all documents.
     */
    protected function _removeDuplicateMetadata()
    {
        $documents = &$this->_processedFiles[$this->_metadataFilepath];

        foreach ($documents as &$document) {
            $document = $this->_removeDuplicateMetadataForRecord($document);
            foreach ($document['files'] as &$file) {
                $file = $this->_removeDuplicateMetadataForRecord($file);
            }
        }
    }

    /**
     * Remove duplicate metadata of a single record.
     *
     * @param array $record A document or a file.
     * @return array
     */
    protected function _removeDuplicateMetadataForRecord($record)
    {
        foreach ($record as $key => &$value) {
            switch ($key) {
                case 'metadata':
                    foreach ($value as $elementSetName => &$elementName) {
                        $elementName = array_map('array_unique', $elementName);
                    }
                    break;

                case 'extra':
                    foreach ($value as &$data) {
                        if (is_array($data)) {
                            $data = array_unique($data);
                        }
                    }
                    break;
            }
        }
        return $record;
    }

    /**
     * Encode a path as RFC [RFC 3986] (same as rawurlencode(), except "/").
     *
     * @param string $relativePath Relative path.
     * @return string
     */
   protected function _rawurlencodeRelativePath($relativePath)
   {
        $paths = explode('/', $relativePath);
        $paths = array_map('rawurlencode', $paths);
        $path = implode('/', $paths);
        return $path;
   }

    /**
     * Determine if a uri is a remote url or a local path.
     *
     * @param string $uri
     * @return boolean
     */
    protected function _isRemote($uri)
    {
        return strpos($uri, 'http://') === 0
            || strpos($uri, 'https://') === 0
            || strpos($uri, 'ftp://') === 0
            || strpos($uri, 'sftp://') === 0;
    }

    /**
     * Determine if a uri is an external url or inside the folder.
     *
     * @param string $uri
     * @return boolean
     */
    protected function _isInsideFolder($uri)
    {
        return strpos($uri, $this->_uri) === 0;
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
        $command = get_option('archive_folder_processor');

        // Default is the internal xslt processor of php.
        return empty($command)
            ? $this->_processXsltViaPhp($input, $stylesheet, $output, $parameters)
            : $this->_processXsltViaExternal($input, $stylesheet, $output, $parameters);
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
    private function _processXsltViaExternal($input, $stylesheet, $output = '', $parameters = array())
    {
        if (empty($output)) {
            $output = tempnam(sys_get_temp_dir(), 'omk_');
        }

        $command = get_option('archive_folder_processor');

        $command = sprintf($command, escapeshellarg($input), escapeshellarg($stylesheet), escapeshellarg($output));
        foreach ($parameters as $name => $parameter) {
            $command .= ' ' . escapeshellarg($name . '=' . $parameter);
        }

        $result = (int) shell_exec($command . ' 2>&- || echo 1');
        @chmod($output, 0640);

        // In Shell, 0 is a correct result.
        return ($result == 1) ? NULL : $output;
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
    private function _processXsltViaPhp($input, $stylesheet, $output = '', $parameters = array())
    {
        if (empty($output)) {
            $output = tempnam(sys_get_temp_dir(), 'omk_');
        }

        try {
            $domXml = $this->_domXmlLoad($input);
            $domXsl = $this->_domXmlLoad($stylesheet);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        $proc = new XSLTProcessor;
        $proc->importStyleSheet($domXsl);
        $proc->setParameter('', $parameters);
        $result = $proc->transformToURI($domXml, $output);
        @chmod($output, 0640);

        return ($result === FALSE) ? NULL : $output;
    }

    /**
     * Load a xml or xslt file into a Dom document via file system or http.
     *
     * @param string $filepath Path of xml file on file system or via http.
     * @return DomDocument or throw error message.
     */
    private function _domXmlLoad($filepath)
    {
        $domDocument = new DomDocument;

        // Default import via file system.
        if (parse_url($filepath, PHP_URL_SCHEME) != 'http' && parse_url($filepath, PHP_URL_SCHEME) != 'https') {
            $domDocument->load($filepath);
        }

        // If xml file is over http, need to get it locally to process xslt.
        else {
            $xmlContent = file_get_contents($filepath);
            if ($xmlContent === false) {
                $message = __('Enable to load "%s". Verify that you have rights to access this folder and subfolders.', $filepath);
                throw new Exception($message);
            }
            elseif (empty($xmlContent)) {
                $message = __('The file "%s" is empty. Process is aborted.', $filepath);
                throw new Exception($message);
            }
            $domDocument->loadXML($xmlContent);
        }

        return $domDocument;
    }

    /**
     * Set the xml format of all documents, specially if a sub class is used.
     */
    protected function _setXmlFormat()
    {
        $documents = &$this->_processedFiles[$this->_metadataFilepath];

        foreach ($documents as &$document) {
            if (isset($document['xml'])) {
                $document['format_xml'] = $this->_formatXml;
            }
            else {
                unset($document['format_xml']);
            }
            if (isset($document['files'])) {
                foreach ($document['files'] as &$file) {
                    if (isset($file['xml'])) {
                        $file['format_xml'] = $this->_formatXml;
                    }
                    else {
                        unset($file['format_xml']);
                    }
                }
            }
        }
    }
}
