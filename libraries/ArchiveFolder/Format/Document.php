<?php
/**
 * Metadata format map for the Document xml format.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Format_Document extends ArchiveFolder_Format_Abstract
{
    const METADATA_PREFIX = 'doc';
    const METADATA_SCHEMA = 'http://localhost/documents.xsd';
    const METADATA_NAMESPACE = 'http://localhost/documents/';

    const DC_PREFIX = 'dc';
    const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    const DCTERMS_PREFIX = 'dcterms';
    const DCTERMS_NAMESPACE = 'http://purl.org/dc/terms/';

    const XML_ROOT = 'documents';

    protected $_metadataPrefix = self::METADATA_PREFIX;
    protected $_metadataSchema = self::METADATA_SCHEMA;
    protected $_metadataNamespace = self::METADATA_NAMESPACE;
    protected $_root = self::XML_ROOT;

    protected $_parametersFormat = array(
        // Force the dc terms.
        'use_dcterms' => true,
        'link_to_files' => false,
        'support_separated_files' => false,
        'compare_directly' => true,
    );

    public function startRoot()
    {
        $writer = $this->_writer;
        $writer->startElement(self::XML_ROOT);
        // $writer->writeAttribute('xmlns', self::METADATA_NAMESPACE);
        // $writer->writeAttribute('xmlns:' . self::DC_PREFIX, self::DC_NAMESPACE);
        // $writer->writeAttribute('xmlns:' . self::DCTERMS_PREFIX, self::DCTERMS_NAMESPACE);
        // $writer->writeAttribute('xmlns:' . self::XSI_PREFIX, self::XSI_NAMESPACE);
        // $writer->writeAttribute('xsi:schemaLocation', self::METADATA_NAMESPACE . ' ' . self::METADATA_SCHEMA);
    }

    /**
     * Create the xml element for the specified file for a main document.
     *
     * @internal The document should be loaded before use of this function.
     *
     * @param array $file
     * @param integer $order
     */
    public function fillFileAsRecord($file, $order)
    {
    }

    protected function _fillMetadata($record = null)
    {
        $writer = $this->_writer;

        // The record is the document because item and files are saved together.
        $record = $this->_document;

        // Prepare the record.
        $writer->startElement('record');
        // No xmlns, because they are the same than the root.
        // $writer->writeAttribute('xmlns', self::METADATA_NAMESPACE);
        // $writer->writeAttribute('xmlns:' . self::XSI_PREFIX, self::XSI_NAMESPACE);
        // $writer->writeAttribute('xsi:schemaLocation', self::METADATA_NAMESPACE . ' ' . self::METADATA_SCHEMA);

        // Exception for tags: write as extra data to avoid complex attributes.
        if (isset($record['specific']['tags'])) {
            $record['extra']['tags'] = $record['specific']['tags'];
            unset($record['specific']['tags']);
        }

        $this->_writeProcessAndSpecific($record);
        $this->_writeMetadata($record);
        $this->_writeExtraData($record);

        if (!empty($record['files'])) {
            foreach ($record['files'] as $file) {
                $writer->startElement('record');
                $this->_writeProcessAndSpecific($file);
                $this->_writeMetadata($file);
                $this->_writeExtraData($file);
                $writer->endElement();
            }
        }

        $writer->endElement();
    }

    protected function _writeProcessAndSpecific($record)
    {
        $writer = $this->_writer;

        // Process data are not written, except the record type and the action.
        $attributes = array();
        foreach ($record['process'] as $key => $value) {
            if (in_array($key, array('record type', 'action'))) {
                $attributes[$key] = $value;
            }
        }

        // The union keeps the former values.
        $attributes += $record['specific'];
        if (empty($attributes)) {
            return;
        }

        foreach ($attributes as $key => $value) {
            // Manage exceptions.
            // TODO Avoid these exceptions.
            // Exception for the file path.
            if ($key == 'path' && $record['process']['record type'] == 'File') {
                $key = 'file';
                // The full path is checked, fully formatted and encoded.
                $value = $record['process']['fullpath'];
            }

            // Exception for the item type.
            elseif ($key == 'item_type_name' && $record['process']['record type'] == 'Item') {
                $key = 'itemType';
            }

            // Normalize keys like "original filename" or "item type".
            else {
                $key = Inflector::variablize($key);
            }

            // Normally, only for tags, but they are now moved into extra.
            if (is_array($value)) {
                $value = implode(',', $value);
            }

            $writer->writeAttribute($key, $value);
        }
    }

    protected function _writeMetadata($record)
    {
        $writer = $this->_writer;

        if (empty($record['metadata'])) {
            return;
        }

        foreach ($record['metadata'] as $elementSetName => $elements) {
            $writer->startElement('elementSet');
            $writer->writeAttribute('name', $elementSetName);
            foreach ($elements as $elementName => $elementTexts) {
                $writer->startElement('element');
                $writer->writeAttribute('name', $elementName);
                foreach ($elementTexts as $elementText) {
                    $this->_writeElement('data', $elementText);
                }
                $writer->endElement();
            }
            $writer->endElement();
        }
    }

    protected function _writeExtraData($record)
    {
        $writer = $this->_writer;

        if (empty($record['extra'])) {
            return;
        }

        $flatArray = $this->_flatArrayDeepNotation($record['extra']);

        $writer->startElement('extra');
        foreach ($flatArray as $key => $content) {
            $value = reset($content);
            $name = key($content);
            $this->_writeElement('data', $value, array('name' => $name));
        }
        $writer->endElement();
    }

    /**
     * Converts a nested array into a flat one with keys in array notation
     * without the deepest integer keys. Keys are trimmed.
     *
     * @param array $array Source array.
     * @return array The flat associative array with keys in array notation
     * except last integer subkeys.
     */
    protected function _flatArrayDeepNotation(array $array)
    {
        $result = $this->_flatArrayNotation($array, true);

        $resultDeep = array();
        // TODO Use a regex.
        foreach ($result as $key => $value) {
            $lastKeyStart = strrpos($key, '[');
            if ($lastKeyStart === false || $lastKeyStart == strlen($key)) {
                $resultDeep[][$key] = $value;
            }
            // Check if there is a last key.
            else {
                $lastKeyEnd = strrpos($key, ']');
                if ($lastKeyEnd === false || $lastKeyEnd <= $lastKeyStart) {
                    $resultDeep[][$key] = $value;
                }
                // There is a last key.
                else {
                    $lastKey = substr($key, $lastKeyStart + 1, $lastKeyEnd - $lastKeyStart - 1);
                    if ($lastKey === '' || $lastKey === '0' || (integer) $lastKey > 0) {
                        $resultDeep[][substr($key, 0, $lastKeyStart)] = $value;
                    }
                    // Keep the original key.
                    else {
                        $resultDeep[][$key] = $value;
                    }
                }
            }
        }

        return $resultDeep;
    }

    /**
     * Converts a nested array into a flat one with keys in array notation. Keys
     * may be trimmed.
     *
     * @param array $array Source array.
     * @param boolean $trimKeys
     * @return array The flat associative array with keys in array notation.
     */
    protected function _flatArrayNotation(array $array, $trimKeys = false)
    {
        $result = array();
        $this->_flatArrayNotationRecursive($array, $result, $trimKeys);
        return $result;
    }

    /**
     * Helper to recursively convert a nested array into a flat one with keys in
     * array notation. Keys may be trimmed.
     *
     * @see https://gist.github.com/wapmorgan/7776761
     *
     * @param array $array Source array.
     * @param array $result Resulting array.
     * @param boolean $trimKeys
     * @param string $previousKey Previous key.
     * @return array The flat associative array with keys in array notation.
     */
    private function _flatArrayNotationRecursive(array $array, array &$result = array(), $trimKeys = false, $previousKey = null)
    {
        foreach ($array as $key => $value) {
            $key = $trimKeys ? trim($key) : $key;
            if (is_array($value)) {
                // First level.
                if (is_null($previousKey)) {
                    $this->_flatArrayNotationRecursive($value, $result, $trimKeys, $key);
                }
                // Intermediate level.
                else {
                    $this->_flatArrayNotationRecursive($value, $result, $trimKeys, $previousKey . '[' . $key . ']');
                }
            }
            // Deepest level.
            else {
                // First level.
                if (is_null($previousKey)) {
                    $result[$key] = $value;
                }
                // With intermediate level.
                else {
                    $result[$previousKey . '[' . $key . ']'] = $value;
                }
            }
        }
    }
}
