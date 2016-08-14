<?php
/**
 * Validation file tool that can be used by other classes.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Tool_ValidateFile
{
    /**
     * Check if the a file is a metadata file via extension and/or content.
     *
     * @param string $filepath The path to the metadata file.
     * @param array $checks The list of check to process.
     * @param array $args The specific values that are needed for some checks.
     * @return boolean
     */
    public function isMetadataFile(
        $filepath,
        $checks = array(),
        $args = array()
    ) {
        $this->_metadataFilepath = $filepath;

        foreach ($checks as $check) {
            switch ($check) {
                case 'false':
                    return false;

                default:
                    $method = '_check' . ucfirst(Inflector::camelize($check));
                    if (!method_exists($this, $method)) {
                        return false;
                    }
                    if (!$this->$method($filepath, $args)) {
                        return false;
                    }
                    break;
            }
        }

        // All tests are ok, or there is no test.
        return true;
    }

    /**
     * Check if the current file is a metadata one.
     *
     * @param string $filepath
     * @param array $args Specific value needed: extension.
     * @return boolean
     */
    protected function _checkExtension($filepath, $args)
    {
        $extension = $args['extension'];
        if (empty($extension)) {
            return false;
        }
        return strtolower(pathinfo($filepath, PATHINFO_EXTENSION)) === $extension;
    }

    /**
     * Check if the current file is a metadata one for a double extension.
     *
     * @param string $filepath
     * @param array $args Specific value needed: extension.
     * @return boolean
     */
    protected function _checkDoubleExtension($filepath, $args)
    {
        $extension = $args['extension'];
        if (empty($extension)) {
            return false;
        }
        $extension = '.' . $extension;
        return substr(strtolower($filepath), strlen($filepath) - strlen($extension)) === $extension;
    }

    /**
     * Quick check if the current file is a xml metadata one.
     *
     * @param string $filepath
     * @param array $args
     * @return boolean
     */
    protected function _checkQuickXml($filepath, $args)
    {
        // XmlReader is the quickest and the simplest for such a check, localy
        // or remotely.
        $reader = new XMLReader;
        $result = $reader->open($filepath, null, LIBXML_NSCLEAN);
        $reader->close();
        return $result;
    }

    /**
     * Check if the current file is a xml metadata one, without validation.
     *
     * @param string $filepath
     * @param array $args Specific value needed: xmlRoot.
     * @return boolean
     */
    protected function _checkRootXml($filepath, $args)
    {
        $xmlRoot = $args['xmlRoot'];
        if (empty($xmlRoot)) {
            return false;
        }

        // XmlReader is the quickest and the simplest for such a check, localy
        // or remotely.
        $reader = new XMLReader;
        $result = $reader->open($filepath, null, LIBXML_NSCLEAN);
        if ($result) {
            // The xml prefix may or may not be used.
            // TODO Use the prefix used in the xml file for the specified namespace.
            $xmlPrefix = $args['xmlPrefix'];
            $xmlPrefixRoot = empty($xmlPrefix) ? '' : $xmlPrefix . ':' . $xmlRoot;

            $result = false;
            while ($reader->read()) {
                if ($reader->name != '#comment') {
                    $result = $reader->name === $xmlRoot
                        || ($xmlPrefixRoot && $reader->name === $xmlPrefixRoot);
                    break;
                }
            }
        }
        $reader->close();
        return $result;
    }

    /**
     * Validate the namespace of a file, without checking root.
     *
     * @param string $filepath
     * @param array $args
     * @return boolean
     */
    protected function _checkNamespaceXml($filepath, $args)
    {
        $xmlNamespace = $args['xmlNamespace'];
        if (empty($xmlNamespace)) {
            return false;
        }

        // XmlReader is the quickest and the simplest for such a check, localy
        // or remotely.
        $reader = new XMLReader;
        $result = $reader->open($filepath, null, LIBXML_NSCLEAN);
        if ($result) {
            // The xml prefix may or may not be used.
            // TODO Use the prefix used in the xml file for the specified namespace.
            $xmlPrefix = $args['xmlPrefix'];
            $xmlPrefixNs = empty($xmlPrefix) ? '' : 'xmlns:' . $xmlPrefix;

            $result = false;
            while ($reader->read()) {
                if ($reader->name != '#comment') {
                    $result = $reader->getAttribute('xmlns') === $xmlNamespace
                        || ($xmlPrefixNs
                            && $reader->getAttribute($xmlPrefixNs) === $xmlNamespace);
                        break;
                }
            }
        }
        $reader->close();
        return $result;
    }

    /**
     * Check if the current file is a xml metadata one.
     *
     * @param string $filepath
     * @param array $args Specific values needed: xmlRoot, namespace.
     * @return boolean
     */
    protected function _checkValidateXml($filepath, $args)
    {
        $xmlRoot = $args['xmlRoot'];
        $xmlNamespace = $args['xmlNamespace'];
        if (empty($xmlRoot) || empty($xmlNamespace)) {
            return false;
        }

        // XmlReader is the quickest and the simplest for such a check, localy
        // or remotely.
        $reader = new XMLReader;
        $result = $reader->open($filepath, null, LIBXML_NSCLEAN);
        if ($result) {
            // The xml prefix may or may not be used.
            // TODO Use the prefix used in the xml file for the specified namespace.
            $xmlPrefix = $args['xmlPrefix'];
            if (empty($xmlPrefix)) {
                $xmlPrefixNs = '';
                $xmlPrefixRoot = '';
            }
            // Check the existing prefix.
            else {
                $xmlPrefixNs = 'xmlns:' . $xmlPrefix;
                $xmlPrefixRoot = $xmlPrefix . ':' . $xmlRoot;
            }

            $result = false;
            while ($reader->read()) {
                if ($reader->name != '#comment') {
                    $result = ($reader->name === $xmlRoot
                            && $reader->getAttribute('xmlns') === $xmlNamespace)
                        || ($xmlPrefixNs
                            && $reader->name === $xmlPrefixRoot
                            && $reader->getAttribute($xmlPrefixNs) === $xmlNamespace);
                    break;
                }
            }
        }
        $reader->close();
        return $result;
    }

    /**
     * Check if the current file is a json one.
     *
     * @param string $filepath
     * @param array $args Specific values needed: xmlRoot, namespace.
     * @return boolean
     */
    protected function _checkJson($filepath, $args)
    {
        $content = file_get_contents($filepath);
        if (empty($content)) {
            return false;
        }

        $json = json_decode($content, true);
        return !is_null($json);
    }
}
