<?php
/**
 * Map metadata text files into Omeka elements for each item and file.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Mapping_Text extends ArchiveFolder_Mapping_Abstract
{
    protected $_checkMetadataFile = array('double extension');
    protected $_extension = 'metadata.txt';

    // Internal variables.
    // Separator and element separator should be different.
    protected $_separator = '=';
    // The continueValue is used for multi-line content, at beginning of a line.
    protected $_continueValue = '  ';

    /**
     * Prepare the list of documents set inside the current metadata file.
     */
    protected function _prepareDocuments()
    {
        $this->_processedFiles[$this->_metadataFilepath] = array();
        $documents = &$this->_processedFiles[$this->_metadataFilepath];

        $content = file_get_contents($this->_metadataFilepath);
        if (empty($content)) {
            return;
        }

        $lines = $this->_getLines($content);

        $this->_extractDocumentsFromLines($lines);
    }

    /**
     * Prepare the list of documents set inside lines of a text.
     *
     * @param array $lines Each lines represents data (element and content).
     * @return void
     */
    protected function _extractDocumentsFromLines($lines)
    {
        $documents = &$this->_processedFiles[$this->_metadataFilepath];

        // The first item may or may not have the "Item" line, so it is set now
        // and it will be removed later if empty.
        $indexDocument = 0;
        $indexFile = 0;
        $documents[$indexDocument] = array();
        $record = &$documents[$indexDocument];
        $record['files'] = array();
        $referencedFiles = &$record['files'];
        foreach ($lines as $line) {
            // Check metadata.
            $posSepareLine = mb_strpos($line, $this->_separator);
            $meta = trim(mb_substr($line, $posSepareLine + 1));

            // Check name (trim the no-break space too).
            $name = trim(mb_substr($line, 0, $posSepareLine));
            $name = $this->_getDataName($name);

            // Comment.
            if (is_null($name)) {
                continue;
            }

            // Prepare element.
            $elementSetName = '';
            $elementName = '';

            // This is a special value.
            if (is_string($name)) {
                $continueForeach = false;
                switch ($name) {
                    // This is an item.
                    case 'name':
                        $indexDocument++;
                        $indexFile = 0;
                        $documents[$indexDocument] = array('name' => $meta);
                        $record = &$documents[$indexDocument];
                        $record['files'] = array();
                        $referencedFiles = &$record['files'];
                        $continueForeach = true;
                        break;

                    // This is a file.
                    case 'files':
                        $indexFile++;
                        $file = array();
                        $file['path'] = $meta;
                        $referencedFiles[$indexFile] = $file;
                        $record = &$referencedFiles[$indexFile];
                        $continueForeach = true;
                        break;

                    // This is something else to add in "extra" metadata.
                    // case 'record type':
                    // case 'item type':
                    default:
                        $elementName = $name;
                        break;
                }
                // Continue the for each loop, that can't be done in the switch.
                if ($continueForeach) {
                    continue;
                }
            }
            // This is a metadata.
            else {
                $elementSetName = $name[0];
                $elementName = $name[1];
            }

            // Record is the main document or files.
            if ($elementSetName === '') {
                $record['extra'][$elementName][] = $meta;
            }
            // Normal metadata.
            else {
                $record['metadata'][$elementSetName][$elementName][] = $meta;
            }
        }

        // Remove the first item if it is empty.
        $first = &$documents[0];
        if (empty($first['metadata']) && empty($first['files']) && empty($first['extra'])) {
            unset($documents[0]);
        }
        // Else set a name if needed.
        elseif (empty($first['name'])) {
            $first['name'] = $this->_managePaths->getRelativePathToFolder($this->_metadataFilepath);
        }

        return $documents;
    }

    /**
     * Get all lines (multiple lines are merged).
     *
     * @param string $content The content of a file.
     * @return array Lines of text where multilines are merged.
     */
    protected function _getLines($content)
    {
        $lines = array();
        $index = 0;
        foreach (explode($this->_endOfLine, $content) as $line) {
            $trimmed = trim($line);
            // Check if the content belongs to the previous field.
            if (mb_strpos($line, $this->_continueValue) === 0) {
                // Check if this is the start of the file, before the first
                // metadata.
                if ($index - 1 < 0) {
                    continue;
                }
                $lines[$index - 1] .= $this->_endOfLine . mb_substr($line, strlen($this->_continueValue));
            }
            // This is an empty line.
            // The check doesn't use empty(), because "0" can be a content.
            elseif (strlen($trimmed) == 0) {
                if ($index - 1 < 0) {
                    continue;
                }
                $lines[$index - 1] .= $this->_endOfLine;
            }
            // This is a comment or a malformatted line (without "=").
            elseif (!mb_strpos($trimmed, $this->_separator)) {
                continue;
            }
            // This is a new line.
            else {
                $lines[$index] = $line;
                $index++;
            }
        }

        return array_map('trim', $lines);
    }
}
