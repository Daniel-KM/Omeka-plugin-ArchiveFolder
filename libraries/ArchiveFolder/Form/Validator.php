<?php
class ArchiveFolder_Form_Validator extends Zend_Validate_Callback
{
    /**
     * Callback to check extra-parameters.
     *
     * @internal The availability will be ckecked just before import.
     *
     * @param string $value The value to check.
     * @return boolean
     */
    public function validateUri($uri)
    {
        $scheme = parse_url($uri, PHP_URL_SCHEME);
        // The check is done via the server for external urls.
        if (in_array($scheme, array('http', 'https', 'ftp', 'sftp'))) {
            return Zend_Uri::check($uri);
        }

        // Unknown or unmanaged scheme.
        if ($scheme != 'file' && $uri[0] != '/') {
            return false;
        }

        // Check the security setting.
        $settings = Zend_Registry::get('archive_folder');
        if ($settings->local_folders->allow != '1') {
            return false;
        }

        // Check the base path.
        $basepath = $settings->local_folders->base_path;
        $realpath = realpath($basepath);
        if ($basepath !== $realpath || strlen($realpath) <= 2) {
            return false;
        }

        // Check the uri.
        if ($settings->local_folders->check_realpath == '1') {
            if (strpos(realpath($uri), $realpath) !== 0
                    || !in_array(substr($uri, strlen($realpath), 1), array('', '/'))
                ) {
                return false;
            }
        }

        // The uri is allowed.
        return true;
    }

    /**
     * Callback to check extra-parameters.
     *
     * @param string $value The value to check.
     * @return boolean
     */
    public function validateExtraParameters($value)
    {
        $value = trim($value);
        if (empty($value)) {
            return true;
        }

        $parametersAdded = array_values(array_filter(array_map('trim', explode(PHP_EOL, $value))));
        $parameterNameErrors = array();
        $parameterValueErrors = array();
        foreach ($parametersAdded as $parameterAdded) {
            if (strpos($parameterAdded, '=') === FALSE) {
                return false;
            }

            list($paramName, $paramValue) = explode('=', $parameterAdded);
            $paramName = trim($paramName);
            if ($paramName == '') {
                return false;
            }
        }

        return true;
    }
}
