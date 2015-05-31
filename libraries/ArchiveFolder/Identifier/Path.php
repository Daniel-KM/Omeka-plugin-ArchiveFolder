<?php
/**
 * Utility class for dealing with identifiers.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Identifier_Path extends ArchiveFolder_Identifier_Abstract
{
    /**
     * Return the identifier of a record from a list of unique order / record.
     *
     * @param array $data Array of associative arrays of order and record.
     * @return string Oai identifier.
     */
    protected function _create($data)
    {
        return $this->_createPath($data);
    }

    /**
     * Wrapper to help to build similar identifiers.
     *
     * @return string Oai identifier.
     */
    protected function _createPath($data, $separator = '/', $addItem = false)
    {
        if (!$addItem) {
            $resource = array_pop($data);
            $data = array($resource);
        }

        $list = array();
        foreach ($data as $resource) {
            $list[] = isset($resource['path']) ? $resource['path'] : $resource['name'];
        }

        // TODO Rebuild a short and cleaned path.
        $id = $this->_getParameter('repository_identifier') . ':' . implode($separator, $list);
        return $id;
    }
}
