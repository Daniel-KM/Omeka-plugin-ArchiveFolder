<?php
/**
 * Utility class for dealing with identifiers.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Identifier_PathItem extends ArchiveFolder_Identifier_Path
{
    /**
     * Return the identifier of a record from a list of unique order / record.
     *
     * @param array $data Array of associative arrays of order and record.
     * @return string Oai identifier.
     */
    protected function _create($data)
    {
        $separator = '/';
        $addItem = true;

        return $this->_createPath($data, $separator, $addItem);
    }
}
