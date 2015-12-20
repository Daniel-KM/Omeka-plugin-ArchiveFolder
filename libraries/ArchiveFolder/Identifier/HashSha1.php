<?php
/**
 * Utility class for dealing with identifiers.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Identifier_HashSha1 extends ArchiveFolder_Identifier_Abstract
{
    /**
     * Return the identifier of a record from a list of unique order / record.
     *
     * @internal Useless, because it makes the document not updatable.
     * @todo Use the hash of the path or name.
     *
     * @param array $data Array of associative arrays of order and record.
     * @return string Oai identifier.
     */
    protected function _create($data)
    {
        return sha1($this->_getParameter('repository_identifier') . ':' . $this->_number . ':' . json_encode($data));
    }
}
