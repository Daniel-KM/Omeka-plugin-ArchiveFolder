<?php

/**
 * @package ArchiveFolder
 */
class ArchiveFolder_Record extends Omeka_Record_AbstractRecord implements Zend_Acl_Resource_Interface
{
    /**
     * @var int The record ID.
     */
    public $id;

    /**
     * @var int The archive folder ID.
     */
    public $folder_id;

    /**
     * @var string The index of the record in the folder.
     *
     * @internal Some records may not have an index (collection), so 0 is used
     * in that case. All other indexes are unique.
     */
    public $index;

    /**
     * @var string The type of the record associated with this folder.
     */
    public $record_type;

    /**
     * @var int The id of the record associated with this folder.
     */
    public $record_id;

    /**
     * Records related to an Item.
     *
     * @var array
     */
    protected $_related = array(
        'Folder' => 'getFolder',
        'Record' => 'getRecord',
    );

    /**
     * Sets the folder.
     *
     * @param ArchiveFolder_Folder|integer
     */
    public function setFolder($folder)
    {
        $this->folder_id = (integer) (is_object($folder) ? $folder->id : $folder);
    }

    /**
     * Sets the index of the record.
     *
     * @param int $index The index of the record.
     */
    public function setIndex($index)
    {
        $this->index = (integer) $index;
    }

    /**
     * Sets the record.
     *
     * @internal Check is done during validation.
     *
     * @param Record $record The record.
     */
    public function setRecord($record)
    {
        $this->setRecordType(get_class($record));
        $this->setRecordId($record->id);
    }

    /**
     * Sets the record type.
     *
     * @internal Check is done during validation.
     *
     * @param int $type The record type.
     */
    public function setRecordType($type)
    {
        $this->record_type = $type;
    }

    /**
     * Sets the record id.
     *
     * @param int $id The record id.
     */
    public function setRecordId($id)
    {
        $this->record_id = (integer) $id;
    }

    /**
     * Returns the folder related to this record.
     *
     * @return ArchiveFolder_Folder.
     */
    public function getFolder()
    {
        return $this->getTable('ArchiveFolder_Folder')->find($this->folder_id);
    }

    /**
     * Get the current record object or the specified one from an array.
     *
     * @internal The record of a folder may be deleted. No check is done.
     *
     * @param array $record The record with record type and record id.
     * @return Record|null The record, else null if deleted.
     */
    public function getRecord($record = null)
    {
        if (is_null($record)) {
            $recordType = $this->record_type;
            $recordId = $this->record_id;
        }
        elseif (is_object($record)) {
            return $record;
        }
        elseif (is_array($record)) {
            // Normal array.
            if (isset($record['record_type']) && isset($record['record_id'])) {
                $recordType = $record['record_type'];
                $recordId = $record['record_id'];
            }
            elseif (isset($record['type']) && isset($record['id'])) {
                $recordType = $record['type'];
                $recordId = $record['id'];
            }
            // One row in the array.
            elseif (count($record) == 1) {
                $recordId = reset($record);
                $recordType = key($record);
            }
            // Two rows in the array.
            elseif (count($record) == 2) {
                $recordType = array_shift($record);
                $recordId = array_shift($record);
            }
            // Record not determinable.
            else {
                return;
            }
        }
        // No record.
        else {
            return;
        }

        // Manage the case where record type has been removed.
        if (class_exists($recordType)) {
            return $this->getTable($recordType)->find($recordId);
        }
    }

    /**
     * Simple validation.
     */
    protected function _validate()
    {
        if (empty($this->folder_id)) {
            $this->addError('folder_id', __('Folder cannot be empty.'));
        }
        if (!in_array($this->record_type, array('File', 'Item', 'Collection'))) {
            $this->addError('record_type', __('Type of record "%s" is not correct.', $this->record_type));
        }
    }

    /**
     * Get a property or special value of this record.
     *
     * @param string $property
     * @return mixed
     */
    public function getProperty($property)
    {
        switch($property) {
            case 'folder':
                return $this->getFolder();
            case 'record':
                return $this->getRecord();
            default:
                return parent::getProperty($property);
        }
    }

    /**
     * Declare the representative model as relating to the record ACL resource.
     *
     * Required by Zend_Acl_Resource_Interface.
     *
     * @return string
     */
    public function getResourceId()
    {
        return 'ArchiveFolder_Records';
    }
}
