<?php
/**
 * Utility class for dealing with identifiers.
 *
 * @package ArchiveFolder
 */
abstract class ArchiveFolder_Identifier_Abstract
{
    protected $_uri;
    protected $_parameters;

    protected $_number = 0;

    /**
     * Save the data of the folder if a format need them.
     */
    public function setFolderData($uri, $parameters)
    {
        $this->_uri = $uri;
        $this->_parameters = $parameters;
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
     * Return the identifier of a record from a list of unique order / record.
     *
     * @param array $data Array of associative arrays of order and record.
     * @return string Oai identifier.
     */
    public function create($data)
    {
        ++$this->_number;
        return $this->_create($data);
    }

    /**
     * Return the identifier of a record from a list of unique order / record.
     *
     * @param array $data Array of associative arrays of order and record.
     * @return string Oai identifier.
     */
    abstract protected function _create($data);
}
