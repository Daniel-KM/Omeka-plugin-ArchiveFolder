<?php

/**
 * Model class for a Archive Folder Record table.
 *
 * @package ArchiveFolder
 */
class Table_ArchiveFolder_Record extends Omeka_Db_Table
{
    /**
     * All records should only be retrieved if they join properly on the folder
     * table.
     *
     * @return Omeka_Db_Select
     */
    public function getSelect()
    {
        $select = parent::getSelect();
        $db = $this->_db;

        $alias = $this->getTableAlias();
        $aliasFolder = $this->_db->getTable('ArchiveFolder_Folder')->getTableAlias();

        $select->joinInner(
            array($aliasFolder => $db->ArchiveFolder_Folder),
            "`$aliasFolder`.`id` = `$alias`.`folder_id`",
            array());

        $select->group($alias . '.id');
        return $select;
    }

    /**
     * Retrieve records associated with a folder.
     *
     * @param Folder|integer|array $folder May be multiple.
     * @param array|string $recordType Optional If given, this will only
     * retrieve these record types.
     * @return array List of archive folder objects.
     */
    public function findByFolder($folder, $recordType = '')
    {
        $alias = $this->getTableAlias();
        $select = $this->getSelect();

        $this->filterByFolder($select, $folder);
        $this->filterByRecordType($select, $recordType);
        $select->order("$alias.index ASC");

        return $this->fetchObjects($select);
    }

    /**
     * Retrieve a record associated with a folder.
     *
     * @param Folder|integer $folder
     * @param integer $index
     * @return Record|array A record or a list of records if index is 0.
     */
    public function findByFolderAndIndex($folder, $index)
    {
        $alias = $this->getTableAlias();
        $select = $this->getSelect();

        $index = (integer) $index;

        $this->filterByFolder($select, $folder);
        $select->where("`$alias`.`index` = ?", $index);
        if (empty($index)) {
            $select->order("$alias.index ASC");
        }

        return $index ? $this->fetchObject($select) : $this->fetchObjects($select);
    }

    /**
     * Retrieve records associated with a folder.
     *
     * @param Folder|integer|array $folder May be multiple.
     * @param Record|array $record
     * @return array List of archive folder objects.
     */
    public function findByFolderAndRecord($folder, $record)
    {
        $alias = $this->getTableAlias();
        $select = $this->getSelect();

        $this->filterByFolder($select, $folder);
        $this->filterByRecord($select, $record);
        $select->order("$alias.index ASC");

        return $this->fetchObjects($select);
    }

    /**
     * Count the records created by the process of a folder.
     *
     * @param Folder|integer|array $folder May be multiple.
     * @return integer Total of created records of the folder.
     */
    public function countByFolder($folder)
    {
        $select = $this->getSelectForCount();
        $this->filterByFolder($select, $folder);
        return $this->getDb()->fetchOne($select);
    }

    /**
     * @param Omeka_Db_Select
     * @param array
     * @return void
     */
    public function applySearchFilters($select, $params)
    {
        $alias = $this->getTableAlias();
        $boolean = new Omeka_Filter_Boolean;
        $genericParams = array();
        foreach ($params as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) == '')) {
                continue;
            }
            switch ($key) {
                case 'folder':
                    $this->filterByFolder($select, $value);
                    break;
                case 'record':
                    $this->filterByRecord($select, $value);
                    break;
                case 'record_type':
                    $this->filterByRecordType($select, $value);
                    break;
                default:
                    $genericParams[$key] = $value;
            }
        }

        if (!empty($genericParams)) {
            parent::applySearchFilters($select, $genericParams);
        }
    }

    /**
     * Apply a entry filter to the select object.
     *
     * @see self::applySearchFilters()
     * @param Omeka_Db_Select $select
     * @param Folder|array|integer $folder One or multiple folder or ids.
     */
    public function filterByFolder(Omeka_Db_Select $select, $folder)
    {
        if (empty($folder)) {
            return;
        }
        if (!is_array($folder)) {
            $folder = array($folder);
        }

        // Reset elements to ids.
        $folders = array();
        foreach ($folder as $f) {
            $folders[] = (integer) (is_object($f) ? $f->id : $f);
        }

        $alias = $this->getTableAlias();
        // One folder.
        if (count($folders) == 1) {
            $select->where("`$alias`.`folder_id` = ?", reset($folders));
        }
        // Multiple folders.
        else {
            $select->where("`$alias`.`folder_id` IN (?)", $folders);
        }
    }

    /**
     * Filter entry by record.
     *
     * @see self::applySearchFilters()
     * @param Omeka_Db_Select $select
     * @param Record|array $record
     */
    public function filterByRecord($select, $record)
    {
        $recordType = '';
        // Manage the case where the record is a new one.
        $recordId = 0;
        if (is_array($record)) {
            if (!empty($record['record_type']) && !empty($record['record_id'])) {
                $recordType = Inflector::classify($record['record_type']);
                $recordId = (integer) $record['record_id'];
            }
        }
        // Convert the record.
        elseif ($record) {
            $recordType = get_class($record);
            $recordId = $record->id ?: 0;
        }

        $alias = $this->getTableAlias();
        $select->where("`$alias`.`record_type` = ?", $recordType);
        $select->where("`$alias`.`record_id` = ?", $recordId);
    }

    /**
     * Apply an element filter to the select object.
     *
     * @see self::applySearchFilters()
     * @param Omeka_Db_Select $select
     * @param string|array $recordType One or multiple record types.
     * May be a "0" for non element change.
     */
    public function filterByRecordType(Omeka_Db_Select $select, $recordType)
    {
        if (empty($recordType)) {
            return;
        }

        // Reset elements to ids.
        if (!is_array($recordType)) {
            $recordType = array($recordType);
        }

        $alias = $this->getTableAlias();
        // One change.
        if (count($recordType) == 1) {
            $select->where("`$alias`.`record_type` = ?", reset($recordType));
        }
        // Multiple changes.
        else {
            $select->where("`$alias`.`record_type` IN (?)", $recordType);
        }
    }
}
