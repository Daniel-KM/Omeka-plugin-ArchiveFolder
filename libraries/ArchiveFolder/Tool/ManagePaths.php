<?php
/**
 * Manage paths: tool that can be used by other classes.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Tool_ManagePaths
{
    protected $_uri;
    protected $_parameters;

    // The full path to current metadata file is required for some functions.
    protected $_metadataFilepath;

    /**
     * Constructor of the class.
     *
     * @param string $uri The uri of the folder.
     * @param array $parameters The parameters to use for the mapping.
     * @return void
     */
    public function __construct($uri, $parameters)
    {
        if (empty($uri)) {
            $message = __('The main uri should be set to use the class "ArchiveFolder_Tool_ManagePaths".');
            throw new Exception($message);
        }
        $this->_uri = $uri;
        $this->_parameters = $parameters;
    }

    /**
     * Get parameter by name.
     *
     * @return mixed Value, if any, else null.
     */
    protected function _getParameter($name)
    {
        return isset($this->_parameters[$name]) ? $this->_parameters[$name] : null;
    }

    public function setMetadataFilepath($metadataFilepath)
    {
        $this->_metadataFilepath = $metadataFilepath;
    }

    /**
     * Returns the relative path for the current metadata file.
     *
     * @todo Check under Windows.
     *
     * @return string The relative path can be empty for root, else it is followed
     * by the directory separator "/" (or "\" for local path under Windows).
     */
    public function getRelativeMetadataPath()
    {
        // This static save all relative metadata path by uri and by path.
        // The sub-array for uri is needed for the tests.
        // The metadataFilePath should be absolute to avoid duplicates.
        static $relativeMetadataPaths = array();

        if (empty($this->_metadataFilepath)) {
            return '';
        }

        if (!isset($relativeMetadataPaths[$this->_uri][$this->_metadataFilepath])) {
            $relativeMetadataPath = pathinfo(trim(substr($this->_metadataFilepath, 1 + strlen($this->_uri))), PATHINFO_DIRNAME);
            if ($relativeMetadataPath == '.' || $relativeMetadataPath == '') {
                $relativeMetadataPaths[$this->_uri][$this->_metadataFilepath] = '';
            }
            // Not root, so add the separator.
            else {
                // Probably needed for Windows compatibility.
                $separator = $this->_getParameter('transfer_strategy') != 'Filesystem'
                    ? '/'
                    : DIRECTORY_SEPARATOR;

                $relativeMetadataPaths[$this->_uri][$this->_metadataFilepath] = $relativeMetadataPath . $separator;
            }
        }

        return $relativeMetadataPaths[$this->_uri][$this->_metadataFilepath];
    }

    /**
     * Returns the absolute encoded path or url.
     *
     * Five cases according to filepath and uri of fhe folder.
     * - url => keep it as it, it is always encoded.
     * - absolute path:
     *      => for url: this is forbidden.
     *      => for local: check it and keep it.
     * - relative path:
     *      => for url: encode it for # and ? and add the uri + relative.
     *      => for local: add the uri + relative and check it.
     *
     * @todo Check under Windows.
     * @todo Add an option to keep all paths inside the folder relative to it.
     *
     * @param string $filepath The path may be relative, absolute, local or url.
     * @return string The absolute path or url. Empty if error.
     */
    public function getAbsoluteUri($filepath)
    {
        if (empty($filepath)) {
            return '';
        }

        // Check if this is already an absolute url.
        if ($this->isRemote($filepath)) {
            return $filepath;
        }

        // Check if this is a relative or an absolute path.
        $absolutePath = $this->getAbsolutePath($filepath);
        if (empty($absolutePath)) {
            return '';
        }

        // This is an absolute path.
        if ($absolutePath == $filepath) {
            if ($this->isRemote($this->_uri)) {
                return '';
            }
            if (!$this->isInsideFolder($filepath)) {
                return '';
            }
            return $absolutePath;
        }

        // The path is relative to the current document.
        // Normalize the relative path.
        $relativeFilepath = $this->getRelativePathToFolder($filepath);
        if (empty($relativeFilepath)) {
            return '';
        }

        if ($this->isRemote($this->_uri)) {
            $path = $this->rawurlencodeRelativePath($relativeFilepath);
            return $this->_uri . '/' . $path;
        }

        return $this->_uri . DIRECTORY_SEPARATOR . $relativeFilepath;
    }

    /**
     * Rebuild the url to a file and url-encode it if wanted.
     *
     * Note: The "#" and the"?" are url-encoded in all cases for easier use.
     *
     * @param string $filepath This is a relative filepath (if url, string is
     * returned as it).
     * @param boolean $urlencode If true, URL-encode according to RFC 3986.
     * This parameter is not used for external urls, that should be already
     * formatted.
     * @return string The url.
     */
    public function getAbsoluteUrl($filepath, $urlencode = true)
    {
        // Check if this is already an url.
        if ($this->isRemote($filepath)) {
            return $filepath;
        }

        if ($urlencode) {
            // Check if it is not already an encoded url.
            if ($this->_getParameter('transfer_strategy') == 'Filesystem') {
                $filepath = $this->rawurlencodeRelativePath($filepath);
            }
            return $this->_uri . '/' . $filepath;
        }

        return $this->_uri . '/' . str_replace(array('#', '?'), array('%23', '%3F'), $filepath);
    }

    /**
     * Return absolute path to the resource.
     *
     * A security check is done for a local path, that should belong to folder.
     *
     * @param string $path The path to check.
     * @return string|null Absolute path to the resource. Null if error.
     */
    public function getAbsolutePath($path)
    {
        // Check if this is an absolute url.
        if ($this->isRemote($path)) {
            return $path;
        }

        // Check if this is an absolute path.
        if (strpos($path, '/') === 0) {
            $absolutePath = $path;
        }
        // This is a relative path.
        else {
            $relativeMetadataPath = $this->getRelativeMetadataPath();
            $relativeFilepath = $relativeMetadataPath . $path;
            $absolutePath = $this->_uri . DIRECTORY_SEPARATOR . $relativeFilepath;
        }

        // Set and check real path for local paths (security).
        if ($this->_getParameter('transfer_strategy') == 'Filesystem'
                && (realpath($absolutePath) != $absolutePath
                    || strpos($absolutePath, $this->_uri) !== 0
                    || substr($absolutePath, strlen($this->_uri), 1) !=  '/'
                )
            ) {
            $absolutePath = null;
        }

        return $absolutePath;
    }

    /**
     * Return path relative to the current folder if it is relative.
     *
     * If they are relative, the paths of the files are relative to the file
     * metadata. This method sets it relative to folder.
     * A security check is done for a local path.
     *
     * @param string $path
     * @return string|null Path relative to folder. Null if error.
     */
    public function getRelativePathToFolder($path)
    {
        // Check if this is an absolute url.
        if ($this->isRemote($path)) {
            // Check if this url is not inside the folder.
            if (strpos($path, $this->_uri) !== false) {
                return $path;
            }

            $relativeFilepath = trim(substr($path, 1 + strlen($this->_uri)));
            $absolutePath = $path;
        }
        // Check if this is an absolute path.
        elseif (strpos($path, '/') === 0) {
            $relativeFilepath = trim(substr($path, 1 + strlen($this->_uri)));
            $absolutePath = $path;
        }
        // This is a relative path.
        else {
            $relativeMetadataPath = $this->getRelativeMetadataPath();
            $relativeFilepath = $relativeMetadataPath . $path;
            $absolutePath = $this->_uri . DIRECTORY_SEPARATOR . $relativeFilepath;
        }

        // Set and check real path for local paths (security).
        if (strpos($absolutePath, '/') === 0) {
            // Here, local paths may be raw url encoded.
            $absolutePath = rawurldecode($absolutePath);
            // Check if the path is in the folder.
            if (realpath($absolutePath) != $absolutePath
                    || strpos($absolutePath, $this->_uri) !== 0
                ) {
                $relativeFilepath = '';
            }
        }
        return $relativeFilepath;
    }

    /**
     * Get url inside the repository folder from a path inside a metadata file.
     *
     * @param string $path File path, local or remote, absolute or relative.
     * @param boolean $absolute If true, returns an absolute url (always if
     * external urls).
     * @param boolean $urlencode If true, URL-encode according to RFC 3986.
     * This parameter is not used for external urls, that should be already
     * formatted.
     * @return string|null Absolute url to the resource inside repository, or
     * original url for external resource. Null if error.
     */
    public function getRepositoryUrlForFile($path, $absolute = true, $urlencode = true)
    {
        // No process for path outside folder (check is done somewhere else).
        $absolutePath = $this->getAbsolutePath($path);
        if (!$this->isInsideFolder($absolutePath)) {
            // An absolute path should be already urlencoded. An external local
            // path will be removed later.
            return $absolutePath;
        }

        $relativeFilepath = $this->getRelativePathToFolder($path);
        if (empty($relativeFilepath)) {
            throw new ArchiveFolder_BuilderException(__('The file path "%s" is not correct.', $path));
        }

        // Check if this is an external url.
        if ($this->isRemote($relativeFilepath)) {
            return $path;
        }

        if ($urlencode) {
            $path = $this->rawurlencodeRelativePath($relativeFilepath);
            return $absolute
                ? $this->_uri . '/' . $path
                : $path;
        }

        // A normal relative path.
        $path = str_replace(array('#', '?'), array('%23', '%3F'), $relativeFilepath);
        return $absolute
            ? $this->_uri . '/' . $path
            : $path;
    }

    /**
     * Encode a path as RFC [RFC 3986] (same as rawurlencode(), except "/").
     *
     * @param string $relativePath Relative path.
     * @return string
     */
   public function rawurlencodeRelativePath($relativePath)
   {
        $paths = explode('/', $relativePath);
        $paths = array_map('rawurlencode', $paths);
        $path = implode('/', $paths);
        return $path;
   }

    /**
     * Determine if a uri is a remote url or a local path.
     *
     * @param string $uri
     * @return boolean
     */
    public function isRemote($uri)
    {
        return strpos($uri, 'http://') === 0
            || strpos($uri, 'https://') === 0
            || strpos($uri, 'ftp://') === 0
            || strpos($uri, 'sftp://') === 0;
    }

    /**
     * Determine if a uri is an external url or inside the folder.
     *
     * @param string $uri
     * @return boolean
     */
    public function isInsideFolder($uri)
    {
        return strpos($uri, $this->_uri) === 0;
    }
}
