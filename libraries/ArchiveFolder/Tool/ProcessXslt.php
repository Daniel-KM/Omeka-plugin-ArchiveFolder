<?php
/**
 * Xsl tool that can be used by other classes.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Tool_ProcessXslt
{
    /**
     * Apply a process (xslt stylesheet) on an input (xml file) and save output.
     *
     * @param string $input Path of input file.
     * @param string $stylesheet Path of the stylesheet.
     * @param string $output Path of the output file. If none, a temp file will
     * be used.
     * @param array $parameters Parameters array.
     * @return string|null Path to the output file if ok, null else.
     * @throws ArchiveFolder_Exception()
     */
    public function processXslt($input, $stylesheet, $output = '', $parameters = array())
    {
        $command = get_option('archive_folder_processor');

        // Parameters should not be null.
        $parameters = $parameters ?: array();

        // The readability is a very common error, so it is checked separately.
        // Furthermore, the input should be local to be processed by php or cli.
        $filepath = $input;
        $isRemote = $this->_isRemote($input);
        if ($isRemote) {
            $filepath = tempnam(sys_get_temp_dir(), basename($input));
            $result = file_put_contents($filepath, file_get_contents($input));
            if (empty($result)) {
                $message = __('The remote file "%s" is not readable or empty.', $input);
                throw new ArchiveFolder_Exception($message);
            }
        }
        elseif (!is_file($filepath) || !is_readable($filepath) || !filesize($filepath)) {
            $message = __('The input file "%s" is not readable.', $filepath);
            throw new ArchiveFolder_Exception($message);
        }

        // Default is the internal xslt processor of php.
        $result = empty($command)
            ? $this->_processXsltViaPhp($filepath, $stylesheet, $output, $parameters)
            : $this->_processXsltViaExternal($filepath, $stylesheet, $output, $parameters);

        if ($isRemote) {
            unlink($filepath);
        }

        return $result;
    }

    /**
     * Apply a process (xslt stylesheet) on an input (xml file) and save output.
     *
     * @param string $input Path of input file.
     * @param string $stylesheet Path of the stylesheet.
     * @param string $output Path of the output file. If none, a temp file will
     * be used.
     * @param array $parameters Parameters array.
     * @return string|null Path to the output file if ok, null else.
     */
    private function _processXsltViaPhp($input, $stylesheet, $output = '', $parameters = array())
    {
        if (empty($output)) {
            $output = tempnam(sys_get_temp_dir(), 'omk_');
        }

        try {
            $domXml = $this->_domXmlLoad($input);
            $domXsl = $this->_domXmlLoad($stylesheet);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        $proc = new XSLTProcessor;
        $proc->importStyleSheet($domXsl);
        $proc->setParameter('', $parameters);
        $result = $proc->transformToURI($domXml, $output);
        @chmod($output, 0640);

        // There is no specific message for error with this processor.
        if ($result === false) {
            $message = __('An error occurs during the xsl transformation of the file "%s" with the sheet "%s".',
                $input, $stylesheet);
            throw new ArchiveFolder_Exception($message);
        }

        return $output;
    }

    /**
     * Load a xml or xslt file into a Dom document via file system or http.
     *
     * @param string $filepath Path of xml file on file system or via http.
     * @return DomDocument or throw error message.
     */
    private function _domXmlLoad($filepath)
    {
        $domDocument = new DomDocument;

        // If xml file is over http, need to get it locally to process xslt.
        if ($this->_isRemote($filepath)) {
            $xmlContent = file_get_contents($filepath);
            if ($xmlContent === false) {
                $message = __('Could not load "%s". Verify that you have rights to access this folder and subfolders.', $filepath);
                throw new ArchiveFolder_Exception($message);
            }
            elseif (empty($xmlContent)) {
                $message = __('The file "%s" is empty. Process is aborted.', $filepath);
                throw new ArchiveFolder_Exception($message);
            }
            $domDocument->loadXML($xmlContent);
        }

        // Default import via file system.
        else {
            $domDocument->load($filepath);
        }

        return $domDocument;
    }

    /**
     * Apply a process (xslt stylesheet) on an input (xml file) and save output.
     *
     * @param string $input Path of input file.
     * @param string $stylesheet Path of the stylesheet.
     * @param string $output Path of the output file. If none, a temp file will
     * be used.
     * @param array $parameters Parameters array.
     * @return string|null Path to the output file if ok, null else.
     */
    private function _processXsltViaExternal($input, $stylesheet, $output = '', $parameters = array())
    {
        if (empty($output)) {
            $output = tempnam(sys_get_temp_dir(), 'omk_');
        }

        $command = get_option('archive_folder_processor');

        $command = sprintf($command, escapeshellarg($input), escapeshellarg($stylesheet), escapeshellarg($output));
        foreach ($parameters as $name => $parameter) {
            $command .= ' ' . escapeshellarg($name . '=' . $parameter);
        }

        $result = shell_exec($command . ' 2>&1 1>&-');
        @chmod($output, 0640);

        // In Shell, empty is a correct result.
        if (!empty($result)) {
            $message = __('An error occurs during the xsl transformation of the file "%s" with the sheet "%s" : %s',
                $input, $stylesheet, $result);
            throw new ArchiveFolder_Exception($message);
        }

        return $output;
    }

    /**
     * Determine if a uri is a remote url or a local path.
     *
     * @param string $uri
     * @return boolean
     */
    private function _isRemote($uri)
    {
        return strpos($uri, 'http://') === 0
            || strpos($uri, 'https://') === 0
            || strpos($uri, 'ftp://') === 0
            || strpos($uri, 'sftp://') === 0;
    }
}
