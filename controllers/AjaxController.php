<?php
/**
 * The ArchiveFolder Ajax controller class.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_AjaxController extends Omeka_Controller_AbstractActionController
{
    /**
     * Controller-wide initialization. Sets the underlying model to use.
     */
    public function init()
    {
        // Don't render the view script.
        $this->_helper->viewRenderer->setNoRender(true);

        $this->_helper->db->setDefaultModelName('ArchiveFolder_Folder');
    }

    /**
     * Handle AJAX requests to delete a record.
     */
    public function deleteAction()
    {
        if (!$this->_checkAjax('delete')) {
            return;
        }

        // Handle action.
        try {
            $id = (integer) $this->_getParam('id');
            $folder = $this->_helper->db->find($id);
            if (!$folder) {
                $this->getResponse()->setHttpResponseCode(400);
                return;
            }
            $folder->delete();
        } catch (Exception $e) {
            $this->getResponse()->setHttpResponseCode(500);
        }
    }

    /**
     * Check AJAX requests.
     *
     * 400 Bad Request
     * 403 Forbidden
     * 500 Internal Server Error
     *
     * @param string $action
     */
    protected function _checkAjax($action)
    {
        // Only allow AJAX requests.
        $request = $this->getRequest();
        if (!$request->isXmlHttpRequest()) {
            $this->getResponse()->setHttpResponseCode(403);
            return false;
        }

        // Allow only valid calls.
        if ($request->getControllerName() != 'ajax'
                || $request->getActionName() != $action
            ) {
            $this->getResponse()->setHttpResponseCode(400);
            return false;
        }

        // Allow only allowed users.
        if (!is_allowed('ArchiveFolder_Index', $action)) {
            $this->getResponse()->setHttpResponseCode(403);
            return false;
        }

        return true;
    }
}
