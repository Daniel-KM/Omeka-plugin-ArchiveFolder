<?php
/**
 * ArchiveFolder_UpdateJob class
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_UpdateJob extends Omeka_Job_AbstractJob
{
    const QUEUE_NAME = 'archive_folder_update';

    private $_folderId;
    private $_processType;
    private $_memoryLimit;

    /**
     * Performs the import task.
     */
    public function perform()
    {
        // Set current user for this long running job.
//        Zend_Registry::get('bootstrap')->bootstrap('Acl');

        $memoryLimit = (integer) get_option('archive_folder_memory_limit');
        if ($memoryLimit) {
            ini_set('memory_limit', $memoryLimit);
        }

        $folder = $this->_getFolder();
        if (empty($folder)) {
            throw new UnexpectedValueException(
                __('Unable to process folder #%d, it does not exist.', $this->_folderId));
        }

        // Resent jobs can remain queued after all the items themselves have
        // been deleted. Skip if that's the case.
        if ($folder->status == ArchiveFolder::STATUS_DELETED) {
            _log('[ArchiveFolder] '. __('The folder for uri "%s" (# %d) was deleted prior to running this job.',
                $folder->uri, $folder->id), Zend_Log::NOTICE);
            return;
        }

        try {
            $folder->process($this->_processType);
        } catch (Exception $e) {
            $message = $e->getMessage();
            $folder->setStatus(ArchiveFolder::STATUS_ERROR);
            $folder->addMessage($message, ArchiveFolder::MESSAGE_CODE_ERROR);
            _log('[ArchiveFolder] '. __('Error when processing folder "%s" (#%d): %s',
                $folder->uri, $folder->id, $message), Zend_Log::ERR);
        }
    }

    public function setFolderId($id)
    {
        $this->_folderId = (integer) $id;
    }

    public function setProcessType($processType)
    {
        $this->_processType = (string) $processType;
    }

    /**
     * Returns the folder to process.
     *
     * @return ArchiveFolder The folder to process
     */
    protected function _getFolder()
    {
        return $this->_db
            ->getTable('ArchiveFolder')
            ->find($this->_folderId);
    }
}
