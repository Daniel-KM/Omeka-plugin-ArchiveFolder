<?php if ($folder): ?>
<div class="folder-status">
    <span class="status <?php echo Inflector::variablize($folder->status); ?>">
        <?php
        switch ($folder->status) {
            case ArchiveFolder_Folder::STATUS_ADDED: $status = __('Folder added'); break;
            case ArchiveFolder_Folder::STATUS_RESET: $status = __('Status reset'); break;
            case ArchiveFolder_Folder::STATUS_QUEUED: $status = __('Process queued'); break;
            case ArchiveFolder_Folder::STATUS_PROGRESS: $status = __('Process in progress'); break;
            case ArchiveFolder_Folder::STATUS_PAUSED: $status = __('Process paused'); break;
            case ArchiveFolder_Folder::STATUS_STOPPED: $status = __('Process stopped'); break;
            case ArchiveFolder_Folder::STATUS_KILLED: $status = __('Process killed'); break;
            case ArchiveFolder_Folder::STATUS_COMPLETED: $status = __('Process completed'); break;
            case ArchiveFolder_Folder::STATUS_DELETED: $status = __('Folder deleted'); break;
            case ArchiveFolder_Folder::STATUS_ERROR: $status = __('Process Error'); break;
            default: $status = __('Error'); break;
        }
        echo html_escape($status);
        ?>
    </span>
</div>
<?php endif; ?>
