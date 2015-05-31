<?php
/**
 * The File controller class.
 *
 * @package ArchiveFolder
 */

 class ArchiveFolder_RequestController extends Omeka_Controller_AbstractActionController
{
    // Requested parameters.
    protected $_repositoryIdentifier;
    protected $_filepath;
    // Resulting registered archive folder, if any.
    protected $_folder;

    /**
     * Initialize the controller.
     */
    public function init()
    {
        $this->_helper->db->setDefaultModelName('ArchiveFolder');
    }

    /**
     * Forward to the 'file' action
     *
     * @see self::browseAction()
     */
    public function indexAction()
    {
        $this->_forward('file');
    }

    /**
     * Return a file from the original folder, or the cached one.
     *
     * Original files are not copied by this plugin, but by the harvester. So
     * this is used only for local files that compose the static repository.
     *
     * The option "cache local files" is checked.
     *
     * @return file The returned file has not been imported, so the original
     * folder should be available.
     */
    public function fileAction()
    {
        // Check post.
        $this->_action = 'file';
        if (!$this->_checkPost()) {
            return $this->_error();
        }

        $file = $this->_folder->getFile($this->_filepath);

        if (empty($file)) {
            return $this->_error();
        }

        if (!file_exists($file) || !is_file($file)) {
            return $this->_error(500, __('The file "%s" is registered in repository "%s", but empty.',
                $this->_filepath, $this->_repositoryIdentifier));
        }

        $this->_sendFile($file);
    }

    /**
     * Return the full static repository xml file that represents a folder.
     *
     * This is used only for integrated static repository, even if remote are
     * built here and available.
     *
     * @return file
     */
    public function folderAction()
    {
        // Check post.
        $this->_action = 'folder';
        if (!$this->_checkPost()) {
            return $this->_error();
        }

        $file = $this->_folder->getLocalRepositoryFilepath();

        if (empty($file)) {
            return $this->_error();
        }

        if (!file_exists($file) || !is_file($file)) {
            return $this->_error(500, __('The static repository xml file of the folder "%s" is empty.',
                $this->_repositoryIdentifier));
        }

        // "text/xml" is required by the protocol, not "application/xml" or something else.
        $this->_sendFile($file, 'text/xml');
    }

    /**
     * Helper to send a xml or a file of the repository only if needed.
     */
    protected function _sendFile($file, $contentType = null)
    {
        $mode = 'inline';
        $filename = pathinfo($file, PATHINFO_BASENAME);
        $contentType = $contentType ?: $this->_getContentType($file);
        $filesize = @filesize($file);
        $filetime = @filemtime($file);

        $this->_helper->viewRenderer->setNoRender();
        $response = $this->getResponse();
        $response->clearBody();

        $ifModifiedSince = $this->getRequest()->getHeader('IF-MODIFIED-SINCE');
        // Simple response if not modified.
        if ($ifModifiedSince && $filetime < strtotime($ifModifiedSince)) {
            $response->setHttpResponseCode(304);
            return;
        }

        // Normal response: send the file.
        $response->setHeader('Content-Disposition', $mode . '; filename="' . $filename . '"', true);
        $response->setHeader('Content-Type', $contentType);
        $response->setHeader('Content-Length', $filesize);
        $response->setHeader('Last-Modified', gmdate('D, d M Y H:i:s T', $filetime));
        $file = file_get_contents($file);
        $response->setBody($file);
    }

    /**
     * Check if the post is good.
     *
     * @return boolean
     */
    protected function _checkPost()
    {
        if (!$this->_getRepositoryIdentifier()) {
            return false;
        }

        if ($this->_action == 'file') {
            if (!$this->_getFilepath()) {
                return false;
            }
       }

        if (!$this->_getArchiveFolder()) {
            return false;
        }

        return true;
    }

    /**
     * Get and set the repository identifier.
     *
     * @return string.
     */
    protected function _getRepositoryIdentifier()
    {
        if (is_null($this->_repositoryIdentifier)) {
            $this->_repositoryIdentifier = $this->getParam('repository');
        }

        return $this->_repositoryIdentifier;
    }

    /**
     * Get and set filepath for a local static repository.
     *
     * @internal The filepath is not checked, but if not existing, an error will
     * be returned.
     *
     * @return string Filepath.
     */
    protected function _getFilepath()
    {
        if (is_null($this->_filepath)) {
            $this->_filepath = $this->getParam('filepath');
        }

        return $this->_filepath;
    }

    /**
     * Get and set the archive folder.
     *
     * @return ArchiveFolder.
     */
    protected function _getArchiveFolder()
    {
        if (is_null($this->_folder)) {
            $identifier = $this->_action == 'folder'
                // Remove the extension ".xml".
                ? substr($this->_repositoryIdentifier, 0, -4)
                : $this->_repositoryIdentifier;
            $this->_folder = $this->_helper->db->findByIdentifier($identifier);
        }

        return $this->_folder;
    }

    /**
     * Set and get file object from the file.
     *
     * @return string
     */
    protected function _getContentType($filepath)
    {
        $ftype = 'application/octet-stream';
        $finfo = @new finfo(FILEINFO_MIME);
        $fres = @$finfo->file($filepath);
        if (is_string($fres) && !empty($fres)) {
           $ftype = $fres;
        }
        return $ftype;
    }

    /**
     * Handle error requests.
     *
     * @param integer $httpCode Optional http code (404 by default)..
     * @param string $message Optional message
     * @return void
     */
    protected function _error($httpCode = 404, $message = '')
    {
        if (empty($message)) {
            $message = __('The requested file is not registered by this OAI-PMH static repository.');
        }
        _log('[ArchiveFolder] '. $message, Zend_Log::NOTICE);
        $this->view->message = $message;

        $this->getResponse()
            ->setHttpResponseCode($httpCode)
            ->setHeader('Reason-Phrase', $message);

        $this->render('error');
    }
}
