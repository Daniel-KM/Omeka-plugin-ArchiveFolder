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
     */
    public function processXslt($input, $stylesheet, $output = '', $parameters = array())
    {
        $command = get_option('archive_folder_processor');

        // Default is the internal xslt processor of php.
        return empty($command)
            ? $this->_processXsltViaPhp($input, $stylesheet, $output, $parameters)
            : $this->_processXsltViaExternal($input, $stylesheet, $output, $parameters);
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

        return ($result === false) ? null : $output;
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

        // Default import via file system.
        if (parse_url($filepath, PHP_URL_SCHEME) != 'http' && parse_url($filepath, PHP_URL_SCHEME) != 'https') {
            $domDocument->load($filepath);
        }

        // If xml file is over http, need to get it locally to process xslt.
        else {
            $xmlContent = file_get_contents($filepath);
            if ($xmlContent === false) {
                $message = __('Enable to load "%s". Verify that you have rights to access this folder and subfolders.', $filepath);
                throw new Exception($message);
            }
            elseif (empty($xmlContent)) {
                $message = __('The file "%s" is empty. Process is aborted.', $filepath);
                throw new Exception($message);
            }
            $domDocument->loadXML($xmlContent);
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

        $result = (int) shell_exec($command . ' 2>&- || echo 1');
        @chmod($output, 0640);

        // In Shell, 0 is a correct result.
        return ($result == 1) ? null : $output;
    }
}
