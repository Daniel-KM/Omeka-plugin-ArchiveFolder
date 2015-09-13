<?php
/**
 * Ingest metadata files into Omeka elements for a file.
 *
 * @package ArchiveFolder
 */
abstract class ArchiveFolder_Ingester_Abstract
{
    protected $_uri;
    protected $_parameters;

    // Tools that will be used.
    protected $_managePaths;
    protected $_validateFile;
    protected $_processXslt;

    // The full path to current metadata file.
    protected $_metadataFilepath;

    // The list of tests to check if a file is a metadata file.
    protected $_checkMetadataFile = array('false');

    // The lower case extension, to check if the file is a metadata one.
    protected $_extension;

    /**
     * Constructor of the class.
     *
     * @param string $uri The uri of the folder.
     * @param array $parameters The parameters to use for the mapping.
     * @return void
     */
    public function __construct($uri, $parameters)
    {
        $this->_uri = $uri;
        $this->_parameters = $parameters;

        $this->_managePaths = new ArchiveFolder_Tool_ManagePaths($uri, $parameters);
        $this->_validateFile = new ArchiveFolder_Tool_ValidateFile();
        $this->_processXslt = new ArchiveFolder_Tool_ProcessXslt();
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
     * Extract metadata from a file to associate them to another file.
     *
     * @param string $filepath The path to the metadata file.
     * @return array Metadata.
     */
    abstract public function extractMetadata($filepath);
}
