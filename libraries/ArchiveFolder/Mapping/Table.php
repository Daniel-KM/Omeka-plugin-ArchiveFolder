<?php
/**
 * Map a table into Omeka elements for each item and file.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Mapping_Table extends ArchiveFolder_Mapping_Abstract
{
    const DEFAULT_ELEMENT_DELIMITER = '|';
    const DEFAULT_EMPTY_VALUE = 'Empty value';

    // False is used, because this is an intermediate class to be used with
    // a spreadsheet.
    protected $_checkMetadataFile = array('false');

    // Element delimiter is used to separate values of the element inside cell.
    protected $_elementDelimiter = ArchiveFolder_Mapping_Table::DEFAULT_ELEMENT_DELIMITER;

    // Empty value allows to discern an empty value and no value.
    protected $_emptyValue = ArchiveFolder_Mapping_Table::DEFAULT_EMPTY_VALUE;

    // Normalized list of headers of the current table, for internal purposes.
    protected $_headers = array();

    // Processed document names. This is required to avoid because the "isset()"
    // test doesn't make difference between a string and a number in the list
    // of added documents.
    protected $_names = array();

    /**
     * Constructor of the class.
     *
     * @param string $uri The uri of the folder.
     * @param array $parameters The parameters to use for the mapping.
     * @return void
     */
    public function __construct($uri, array $parameters)
    {
        // For compatibility, "file" is allowed as header to import multiple
        // files attached to an item. The header "files" is allowed too.
        $this->_specialData['file'] = true;
        $this->_specialData['files'] = true;

        parent::__construct($uri, $parameters);
    }

    /**
     * Prepare the list of documents set inside the current metadata file.
     */
    protected function _prepareDocuments()
    {
        // Nothing, because this class should be extended by a specific format.
    }

    /**
     * Add the documents that a table contains to the list of documents.
     *
     * @internal One row can represent multiple records and multiple rows can
     * represent one record.
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
        $this->_elementDelimiter = $this->_getParameter('element_delimiter') ?: ArchiveFolder_Mapping_Table::DEFAULT_ELEMENT_DELIMITER;
        $this->_emptyValue = $this->_getParameter('empty_value') ?: ArchiveFolder_Mapping_Table::DEFAULT_EMPTY_VALUE;

        // Remove headers from the table.
        $key = key($table);
        unset($table[$key]);

        // Fill documents.
        $referencedFiles = null;
        foreach ($table as $row) {
            // Reset the document key.
            $documentKey = null;

            // First step: get the current record from row in order to convert
            // it into a true record in the next step.
            $record = $this->_getRecordFromRow($row);

            // Quick check if the document is empty.
            if (empty($record['metadata'])
                    && empty($record['files'])
                    && empty($record['extra'])
                    && empty($record['process']['name'])
                    && empty($record['specific']['item type'])
                ) {
                continue;
            }

            // Next steps depend on the record type, so it is checked.
            $recordType = $this->_getRecordType($record);
            $record['process']['record type'] = $recordType;
            switch ($recordType) {
                case 'File':
                    // The path and the name will be validated later, but the
                    // path should exist.
                    $record['specific']['path'] = empty($record['specific']['files']) ? null : array_pop($record['specific']['files']);
                    if (!strlen($record['specific']['path'])) {
                        // TODO Another identifier can be used for an update.
                        throw new ArchiveFolder_BuilderException(__('There is no path for the file.'));
                    }
                    unset($record['specific']['files']);

                    // Add a new record or update an existing one.


                    // Process actions by row.
                    if (in_array('action', $this->_headers)) {
                        $documents[] = $record;
                        end($documents);
                        $documentKey = key($documents);
                    }

                    // Process by the whole table: merge rows.
                    else {
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
                        elseif (!empty($record['process']['name']) && isset($this->_names[$record['process']['name']])) {
                            $documentKey = $this->_names[$record['process']['name']];
                            $referencedFiles = &$documents[$documentKey]['files'];
                        }
                        // Else, this is the current referenced files.

                        // The name is a metadata for the document, not the file.
                        unset($record['process']['name']);

                        // Check if this an update of a file in order update it.
                        $referencedFiles[$record['specific']['path']] = isset($referencedFiles[$record['specific']['path']])
                            // This is an update.
                            ? $this->_mergeRecords($referencedFiles[$record['specific']['path']], $record)
                            // This is a new record.
                            : $record;
                    }

                    break;

                case 'Item':
                    // No files.
                    if (empty($record['specific']['files'])) {
                        $record['files'] = array();
                    }
                    // Pre-normalize files.
                    else {
                        foreach ($record['specific']['files'] as $filepath) {
                            $record['files'][$filepath]['specific']['path'] = $filepath;
                        }
                    }
                    unset($record['specific']['files']);

                    // No break: continue for other metadata.

                case 'Collection':
                    // Process actions by row.
                    if (in_array('action', $this->_headers)) {
                        $documents[] = $record;
                        end($documents);
                        $documentKey = key($documents);
                    }

                    // Process by the whole table: merge rows.
                    else {
                        // Add a new record or update an existing one.
                        // If the required name is not filled, this is a new record.
                        if (empty($record['process']['name'])) {
                            $documents[] = $record;
                            end($documents);
                            $documentKey = key($documents);
                        }

                        // Check if this is an update of a previous row.
                        else {
                            // Check if this is a complement in order to merge it.
                            if (isset($this->_names[$record['process']['name']])) {
                                $documentKey = $this->_names[$record['process']['name']];
                                $documents[$documentKey] = $this->_mergeRecords($documents[$documentKey], $record);
                            }
                            // This is a new document.
                            else {
                                $documents[] = $record;
                                end($documents);
                                $documentKey = key($documents);
                            }

                            // Remember the current record name as key if needed.
                            $this->_names[$record['process']['name']] = $documentKey;
                        }
                    }

                    // Remember the current record for next files, if needed.
                    if ($recordType == 'Item') {
                        $referencedFiles = &$documents[$documentKey]['files'];
                    }
                    break;
            }
        }

        foreach ($documents as &$document) {
            $document['extra'] = array_map(array($this, '_normalizeAsStringOrArray'), $document['extra']);
            if (!empty($documents['files'])) {
                foreach ($documents['files'] as &$file) {
                    $file['extra'] = array_map(array($this, '_normalizeAsStringOrArray'), $file['extra']);
                }
            }
        }
    }

    /**
     * Helper to normalize extra values as single or multiple values.
     *
     * Empty values are kept. Duplicates are removed later.
     *
     * @param array $value
     * @return string|array The normalized value as string or array.
     */
    protected function _normalizeAsStringOrArray($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        if (count($value) == 0) {
            return '';
        }
        // Single value is converted into a string.
        if (count($value) == 1) {
            return reset($value);
        }
        // Filtering is possible for arrays.
        return array_filter($value, function($v) {return strlen($v) > 0;});
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

        $current = array();

        // Set default values to avoid notices.
        $current['process'] = array();
        $current['specific'] = array();
        $current['metadata'] = array();
        $current['extra'] = array();

        foreach ($headers as $index => $header) {
            //Check if this a comment.
            if (is_null($header)) {
                continue;
            }

            // This check doesn't use empty(), because "0" can be a content.
            if (!isset($row[$index]) || strlen($row[$index]) == 0) {
                continue;
            }

            // Check if this is a special value (already normalized as string).
            if (is_string($header)) {
                // Separate headers for process and specific headers.
                $recordPart = in_array($header, array('record type', 'action', 'name', 'identifier field', 'internal id'))
                    ? 'process'
                    : 'specific';

                // Multiple values are allowed (for example tags). Keep order.
                if ($this->_specialData[$header]) {
                    // Manage the tags and files exception.
                    if ($header == 'tag') {
                        $header = 'tags';
                    }
                    if ($header == 'file' || $header == 'path') {
                        $header = 'files';
                    }

                    $current[$recordPart][$header] = isset($current[$recordPart][$header])
                        ? $this->_addValues($row[$index], $current[$recordPart][$header])
                        : $this->_addValues($row[$index]);
                }
                // Only one value is allowed: keep last value (even if there is
                // a delimiter).
                else {
                    $current[$recordPart][$header] = $row[$index];
                }
            }

            // This is an array, so this is a standard or an extra metadata.
            else {
                // Check if this a standard or an unrecognized element.
                if (empty($header[0])) {
                    $current['extra'][$header[1]] = isset($current['extra'][$header[1]])
                        ? $this->_addValues($row[$index], $current['extra'][$header[1]])
                        : $this->_addValues($row[$index]);
                }
                // Standard element.
                else {
                    $current['metadata'][$header[0]][$header[1]] = isset($current['metadata'][$header[0]][$header[1]])
                        ? $this->_addValues($row[$index], $current['metadata'][$header[0]][$header[1]])
                        : $this->_addValues($row[$index]);
                }
            }
        }

        return $current;
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
        // Convert a null into an array when needed.
        $result = $existing ?: array();

        if (empty($this->_elementDelimiter) || strpos($value, $this->_elementDelimiter) === false) {
            $result[] = $value;
        }
        // Import each sub-value. No filtering.
        else {
            $values = explode($this->_elementDelimiter, $value);
            $values = array_map('trim', $values);
            foreach ($values as $key => $value) {
                $result[] = $value;
            }
        }

        // Replace empty values by an empty string that will be kept.
        if ($this->_emptyValue !== '') {
            $result = array_map(function($v) {
                return $v === $this->_emptyValue? '' : $v;
            }, $result);
        }

        return $result;
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

            // A merge may be needed.
            else {
                switch ($key) {
                    case 'process':
                    case 'specific':
                        foreach ($value as $k => $v) {
                            // Multiple values.
                            if ($this->_specialData[$k]) {
                                $current[$key][$k] = array_merge_recursive($current[$key][$k], $v);
                            }
                            // Single value.
                            else {
                                $current[$key][$k] = is_array($v) ? array_pop($v) : $v;
                            }
                        }
                        break;

                    case 'metadata':
                    case 'extra':
                        $current[$key] = array_merge_recursive($current[$key], $value);
                        break;

                    case 'files':
                        // This is not recursive, because a file doesn't contain files.
                        $current[$key] = $this->_mergeRecords($current[$key], $value);
                        break;
                }
            }
        }

        return $current;
    }

    /**
     * Determine the type of the record extracted from a row (item, file or
     * collection), without any context, except if required.
     *
     * @internal Here, the record is not normalized.
     *
     * @param array $record The record extracteed from row, not normalized.
     * @return string "Collection, ""Item" or "File".
     */
    private function _getRecordType($record)
    {
        // Check if there is a "record type".
        if (!empty($record['process']['record type'])) {
            $recordType = ucfirst(strtolower($record['process']['record type']));
            if (!in_array($recordType, array('File', 'Item', 'Collection'))) {
                throw new ArchiveFolder_BuilderException(__('The record type "%s" is incorrect.', $record['process']['record type']));
            }
            return $recordType;
        }

        // Check if there is a content specific to an item.
        if (!empty($record['specific']['collection'])
                || !empty($record['specific']['item type'])
                || !empty($record['specific']['tags'])
            ) {
            return 'Item';
        }

        // Check if there is a content specific to an item or a collection.
        if (!empty($record['specific']['public'])
                || !empty($record['specific']['featured'])
            ) {
            // Not fully determinable, so force to Item.
            // TODO Warn for missing data.
            return 'Item';
        }

        // Check if there is a content specific to a file.
        if (!empty($record['specific']['item'])
                || !empty($record['specific']['path'])
                || !empty($record['specific']['original filename'])
                || !empty($record['specific']['filename'])
                || !empty($record['specific']['md5'])
                || !empty($record['specific']['authentication'])
            ) {
            return 'File';
        }

        // Check if there is no file or multiple files.
        if (empty($record['specific']['files']) || count($record['specific']['files']) > 1) {
            return 'Item';
        }

        // TODO Check if still relevant and useful.

        // Check if this is an indexed or an ordered table.
        // Indexed.
        if (in_array('name', $this->_headers)) {
            // Check if there is no name.
            if (empty($record['process']['name'])) {
                return 'Item';
            }

            // Here, there are a document name, one filepath, no record type and no
            // item type, so a simple context of previous rows is needed: the item
            // should be defined before a file.
            if (isset($this->_names[$record['process']['name']])) {
                return 'File';
            }
        }
        // Ordered.

        // TODO Warn for missing data.
        return 'Item';
    }
}
