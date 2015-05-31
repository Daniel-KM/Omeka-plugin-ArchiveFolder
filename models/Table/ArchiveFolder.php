<?php

/**
 * Model class for a Archive Folder table.
 *
 * @package ArchiveFolder
 */
class Table_ArchiveFolder extends Omeka_Db_Table
{
    /**
     * Retrieve a folder by its uri, that should be unique.
     *
     * @uses Omeka_Db_Table::getSelectForFindBy()
     * @param string $uri
     * @return ArchiveFolder|null The existing folder or null.
     */
    public function findByUri($uri)
    {
        $select = $this->getSelectForFindBy(array(
            'uri' => $uri,
        ));
        return $this->fetchObject($select);
    }

    /**
     * Retrieve a folder by the identifier value, that should be unique.
     *
     * @uses Omeka_Db_Table::getSelectForFindBy()
     * @param string $identifier
     * @return ArchiveFolder|null The existing folder or null.
     */
    public function findByIdentifier($identifier)
    {
        $select = $this->getSelectForFindBy(array(
            'identifier' => $identifier,
        ));
        return $this->fetchObject($select);
    }

    /**
     * Get current status of a folder.
     *
     * @param integer $id
     * @return string|null The current status of the folder or null.
     */
    public function getCurrentStatus($id)
    {
        $alias = $this->getTableAlias();
        $select = $this->getSelect();
        $select->reset(Zend_Db_Select::COLUMNS);
        $select->from(array(), array($alias . '.status'));
        $select->where($alias . '.id = ?', $id);
        return $this->fetchOne($select);
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
                case 'owner_id':
                    $this->filterByUser($select, $value, 'owner_id');
                    break;
                case 'status':
                    switch ($value) {
                        case 'ready':
                            $genericParams['status'] = array(
                                ArchiveFolder::STATUS_ADDED,
                                ArchiveFolder::STATUS_RESET,
                                ArchiveFolder::STATUS_PAUSED,
                                ArchiveFolder::STATUS_STOPPED,
                                ArchiveFolder::STATUS_KILLED,
                                ArchiveFolder::STATUS_COMPLETED,
                            );
                            break;
                        case 'processing':
                            $genericParams['status'] = array(
                                ArchiveFolder::STATUS_QUEUED,
                                ArchiveFolder::STATUS_PROGRESS,
                                ArchiveFolder::STATUS_PAUSED,
                            );
                            break;
                        default:
                            $genericParams['status'] = $value;
                            break;
                    }
                    break;
                default:
                    $genericParams[$key] = $value;
            }
        }

        if (!empty($genericParams)) {
            parent::applySearchFilters($select, $genericParams);
        }
    }
}
