<?php
/**
 * Utility class for dealing with identifiers.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Identifier_Position extends ArchiveFolder_Identifier_Abstract
{
    /**
     * Return the identifier of a record from a list of unique order / record.
     *
     * @internal Position is usable for update only if new documents are listed
     * at end.
     *
     * @param array $data Array of associative arrays of order and record.
     * @return string Oai identifier.
     */
    protected function _create($data)
    {
        $id = $this->_getParameter('repository_identifier') . ':' . $this->_number;
        return $id;
    }
}
