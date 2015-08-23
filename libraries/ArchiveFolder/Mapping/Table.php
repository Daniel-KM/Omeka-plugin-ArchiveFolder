<?php
/**
 * Map a table into Omeka elements for each item and file.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Mapping_Table extends ArchiveFolder_Mapping_Abstract
{
    // False is used, because this is an intermediate class to be used with
    // a spreadsheet.
    protected $_checkMetadataFile = array('false');

    // Element delimiter is used to separate values of the element inside cell.
    protected $_elementDelimiter = '';

    // Normalized list of headers of the current table, for internal purposes.
    protected $_headers = array();

    // Processed document names. This is required to avoid because the "isset()"
    // test doesn't make difference between a string and a number in the list
    // of added documents.
    protected $_names = array();

    /**
     * Prepare the list of documents set inside the current metadata file.
     */
    protected function _prepareDocuments()
    {
        // Nothing, because this class should be extended.
    }

    /**
     * Add the documents that a table contains to the list of documents.
     *
     * @param array $table An array of data.
     * @return void
     */
    protected function _addDocumentsFromTable($table)
    {
        $documents = &$this->_processedFiles[$this->_metadataFilepath];

        if (empty($table)) {
            return;
        }

        // Extract and normalize headers.
        $headers = &$this->_headers;
        $headers = reset($table);
        $headers = array_map(array($this, '_getDataName'), $headers);
        $headers = array_values($headers);
        if (empty($headers)) {
            return;
        }

        // Set the element delimiter if any.
        $this->_elementDelimiter = $this->_getParameter('element_delimiter') ?: '';

        // Remove headers from the table.
        $key = key($table);
        unset($table[$key]);

        // Fill documents.
        $referencedFiles = null;
        foreach ($table as $row) {
            // Reset the document key.
            $documentKey = null;

            // First: get the current record from row in order to convert it
            // into a true record in the next step.
            $record = $this->_getRecordFromRow($row);

            // Quick check if the document is empty.
            if (empty($record['metadata'])
                    && empty($record['files'])
                    && empty($record['extra'])
                    && empty($record['name'])
                    && empty($record['item type'])
                ) {
                continue;
            }

            // Next steps depend on the record type.
            $recordType = $this->_getRecordType($record);
            unset($record['record type']);
            switch ($recordType) {
                case 'Item':
                    // Second: normalize the current record.
                    if (isset($record['files'])) {
                        $files = $record['files'];
                        $record['files'] = array();
                        foreach ($files as $filepath) {
                            $record['files'][$filepath] = array('path' => $filepath);
                        }
                    }
                    // No files.
                    else {
                        $record['files'] = array();
                    }

                    // Third: add a new record or update an existing one.
                    // If the required name is not filled, this is a new record.
                    if (empty($record['name'])) {
                        $documents[] = $record;
                        end($documents);
                        $documentKey = key($documents);
                    }
                    // Check if this is an update of a document.
                    else {
                        // Check if this is a complement in order to merge it.
                        if (isset($this->_names[$record['name']])) {
                            $documentKey = $this->_names[$record['name']];
                            $documents[$documentKey] = $this->_mergeRecords($documents[$documentKey], $record);
                        }
                        // This is a new document.
                        else {
                            $documents[] = $record;
                            end($documents);
                            $documentKey = key($documents);
                        }

                        // Remember the current record name as key if needed.
                        $this->_names[$record['name']] = $documentKey;
                    }

                    // Remember the current record for next files, if needed.
                    $referencedFiles = &$documents[$documentKey]['files'];
                    break;

                case 'File':
                    // Second: normalize the current record.
                    // The path and the name will be validated later, but the
                    // path should exist.
                    $record['path'] = empty($record['files']) ? null : reset($record['files']);
                    if (!strlen($record['path'])) {
                        throw new ArchiveFolder_BuilderException(__('There is no path for the file.'));
                    }
                    unset($record['files']);

                    // Third: add a new record or update an existing one.

                    // Check if there are referenced files (normally useless,
                    // except if an ordered table starts with a file).
                    if (is_null($referencedFiles)) {
                        $documents[] = array('files' => array());
                        end($documents);
                        $documentKey = key($documents);
                        $referencedFiles = &$documents[$documentKey]['files'];
                    }
                    // Get the referenced files if there is a name.
                    // It may repeat _getRecordType(), etc., but this is needed.
                    elseif (!empty($record['name']) && isset($this->_names[$record['name']])) {
                        $documentKey = $this->_names[$record['name']];
                        $referencedFiles = &$documents[$documentKey]['files'];
                    }
                    // Else, this is the current referenced files.

                    // The name is a metadata for the document, not the file.
                    unset($record['name']);

                    // Check if this an update of a file in order update it.
                    $referencedFiles[$record['path']] = isset($referencedFiles[$record['path']])
                        // This is an update.
                        ? $this->_mergeRecords($referencedFiles[$record['path']], $record)
                        // This is anew record.
                        : $record;
                    break;

                default:
                    throw new ArchiveFolder_BuilderException(__('The record type "%s" is not managed.', $recordType));
            }
        }
    }

    /**
     * Get a record from a row, without any context.
     *
     * @internal The key "files" is a simple list of paths, because the record
     * type can't be determined here.
     *
     * @param array $row
     * @return array The record.
     */
    protected function _getRecordFromRow($row)
    {
        $headers = &$this->_headers;

        $record = array();

        foreach ($headers as $index => $header) {
            //Check if this a comment.
            if (is_null($header)) {
                continue;
            }

            // This check doesn't use empty(), because "0" can be a content.
            if (!isset($row[$index]) || strlen($row[$index]) == 0) {
                continue;
            }

            // Check if this is a special value.
            if (is_string($header)) {
                switch ($header) {
                    // Single values that are processed here.
                    case 'name':
                    case 'record type':
                        $record[$header] = $row[$index];
                        break;

                    // Multiple values that are managed here.
                    case 'files':
                        $record[$header] = isset($record[$header])
                            ? $this->_addValues($row[$index], $record[$header])
                            : $this->_addValues($row[$index]);
                        break;

                    // Item type is an exception.
                    case 'item type':
                        // The check avoids a notice.
                        $record['extra'][$header] = isset($record['extra'][$header])
                            ? $this->_addValues($row[$index], $record['extra'][$header])
                            : $this->_addValues($row[$index]);
                        break;

                    // Other special headers are removed.
                }
            }

            // This is an array, so this is standard or special metadata.
            else {
                // Check if this a standard or an unrecognized element.
                if (empty($header[0])) {
                    $record['extra'][$header[1]] = isset($record['extra'][$header[1]])
                        ? $this->_addValues($row[$index], $record['extra'][$header[1]])
                        : $this->_addValues($row[$index]);
                }
                // Standard element.
                else {
                    $record['metadata'][$header[0]][$header[1]] = isset($record['metadata'][$header[0]][$header[1]])
                        ? $this->_addValues($row[$index], $record['metadata'][$header[0]][$header[1]])
                        : $this->_addValues($row[$index]);
                }
            }
        }

        return $record;
    }

    /**
     * Check if a cell is multivalued and add them to existing values.
     *
     * @param string $value The value can be multivalued with the separator.
     * @param array $existing
     * @return array The list of existing and new values.
     */
    protected function _addValues($value, $existing = array())
    {
        // Convert a null tino an array when needed.
        $existing = $existing ?: array();

        if (empty($this->_elementDelimiter)) {
            $existing[] = $value;
        }
        // Import each sub-value.
        else {
            $values = explode($this->_elementDelimiter, $value);
            $values = array_map('trim', $values);
            foreach ($values as $value) {
                if (strlen($value) > 0) {
                    $existing[] = $value;
                }
            }
        }

        return $existing;
    }

    /**
     * Check if a cell is multivalued and add them to existing values.
     *
     * @internal Duplicate values are removed later.
     *
     * @param string $value The value can be multivalued with the separator.
     * @param array $existing
     * @return array The list of existing and new values.
     */
    protected function _mergeRecords($current, $new)
    {
        foreach ($new as $key => $value) {
            // No merge is needed.
            if (!isset($current[$key])) {
                $current[$key] = $value;
            }

            // A merge is needed.
            else {
                switch ($key) {
                    case 'metadata':
                    case 'extra':
                        $current[$key] = array_merge_recursive($current[$key], $value);
                        break;

                    case 'files':
                        // This is not recursive, because a file doesn't contain files.
                        $current[$key] = $this->_mergeRecords($current[$key], $value);
                        break;

                    default:
                        $current[$key] = $value;
                        break;
                }
            }
        }

        return $current;
    }

    /**
     * Determine if a record extracted from a row is an item or a file, without
     * any context, except if required.
     *
     * Notes:
     * - Only items and files are managed by a static repository. One static
     * repository is one collection.
     * - Metadata of an item should be set before attached files ones).
     *
     * @param array $record
     * @return string "Item" or "File".
     */
    protected function _getRecordType($record)
    {
        // Check if there is a "record type".
        if (!empty($record['record type'])) {
            return strtolower($record['record type']) == 'file' ? 'File' : 'Item';
        }

        // Check if there is an "item type".
        if (!empty($record['extra']['item type'])) {
            return 'Item';
        }

        // Check if there is no file or multiple files.
        if (empty($record['files']) || count($record['files']) > 1) {
            return 'Item';
        }

        // Check if this is an indexed or an ordered table.
        // Indexed.
        if (in_array('name', $this->_headers)) {
            // Check if there is no name.
            if (empty($record['name'])) {
                return 'Item';
            }

            // Here, there are a document name, one filepath, no record type and no
            // item type, so a simple context of previous rows is needed: the item
            // should be defined before a file.
            if (isset($this->_names[$record['name']])) {
                return 'File';
            }
        }
        // Ordered.

        return 'Item';
    }
}
