<?php
$totalRecords = total_records('ArchiveFolder_Folder');
$pageTitle = __('Archive Folders (%d total)', $total_results);
queue_css_file('archive-folder');
queue_js_file('archive-folder-browse');
echo head(array(
    'title' => $pageTitle,
    'bodyclass' => 'archive-folder browse',
));
?>
<div id="primary">
    <?php if (is_allowed('ArchiveFolder_Index', 'add')): ?>
    <div class="right">
        <a href="<?php echo html_escape(url('archive-folder/index/add')); ?>" class="add button small green"><?php echo __('Add a new archive folder'); ?></a>
    </div>
    <?php endif; ?>
    <h2><?php echo __('Status of Archive Folders'); ?></h2>
    <?php echo flash(); ?>
<?php if (iterator_count(loop('ArchiveFolder_Folder'))): ?>
    <form action="<?php echo html_escape(url('archive-folder/index/batch-edit')); ?>" method="post" accept-charset="utf-8">
        <div class="table-actions batch-edit-option">
            <?php if (is_allowed('ArchiveFolder_Index', 'edit')): ?>
            <input type="submit" class="small green batch-action button" name="submit-batch-check" value="<?php echo __('Check'); ?>">
            <input type="submit" class="small green batch-action button" name="submit-batch-process" value="<?php echo __('Process'); ?>">
            <?php endif; ?>
            <?php
                $actionUri = $this->url(array(
                        'action' => 'browse',
                    ),
                    'default');
                $action = __('Refresh page');
                ?>
            <a href="<?php echo html_escape($actionUri); ?>" class="refresh button blue"><?php echo $action; ?></a>
            <?php if (is_allowed('ArchiveFolder_Index', 'delete')): ?>
            <input type="submit" class="small red batch-actiorran button" name="submit-batch-delete" value="<?php echo __('Delete'); ?>">
            <?php endif; ?>
        </div>
        <?php echo common('quick-filters'); ?>
        <div class="pagination"><?php echo $paginationLinks = pagination_links(); ?></div>
        <table id="archive-folders">
            <thead>
                <tr>
                    <?php if (is_allowed('ArchiveFolder_Index', 'edit')): ?>
                    <th class="batch-edit-heading"><?php // echo __('Select'); ?></th>
                    <?php endif;
                    $browseHeadings[__('Folder')] = 'uri';
                    $browseHeadings[__('Folder Status')] = 'status';
                    $browseHeadings[__('Action')] = null;
                    $browseHeadings[__('Last Modified')] = 'modified';
                    echo browse_sort_links($browseHeadings, array('link_tag' => 'th scope="col"', 'list_tag' => ''));
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php $key = 0; ?>
                <?php foreach (loop('ArchiveFolder_Folder') as $folder): ?>
                <tr class="archive-folder <?php if (++$key%2 == 1) echo 'odd'; else echo 'even'; ?>">
                    <?php if (is_allowed('ArchiveFolder_Index', 'edit')): ?>
                    <td class="batch-edit-check" scope="row" href="<?php echo ADMIN_BASE_URL; ?>" id="archive-folder-<?php echo $folder->id; ?>">
                        <input type="checkbox" name="folders[]" value="<?php echo $folder->id; ?>" />
                    </td>
                    <?php endif; ?>
                    <td><?php
                        echo html_escape($folder->uri); ?>
                        <br />
                        <?php if (empty($folder->messages)): ?>
                            <div class="details">
                                <?php  echo __('No message'); ?>
                            </div>
                        <?php else: ?>
                            <ul class="action-links group">
                                <li>
                                    <a href="<?php echo ADMIN_BASE_URL; ?>" class="archive-folder-details"><?php echo __('Last Messages'); ?></a>
                                </li>
                                <li>
                                    <a href="<?php echo ADMIN_BASE_URL . '/archive-folder/index/logs/id/'. $folder->id; ?>"><?php echo __('All Messages'); ?></a>
                                </li>
                            </ul>
                            <div class="details" style="display: none;">
                                <?php  echo nl2br(str_replace(']', "]\n", substr($folder->messages, -1000))); ?>
                            </div>
                            <div class="last-message" style="display: auto;">
                                <?php
                                    $pos = strrpos($folder->messages, PHP_EOL . '[');
                                    echo $pos ? nl2br(substr($folder->messages, $pos + 1)) : $folder->messages;
                                ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                            echo common('archive-folder-status', array('folder' => $folder));
                        ?>
                    </td>
                    <td>
                    <?php
                        switch ($folder->status):
                            case ArchiveFolder_Folder::STATUS_QUEUED:
                            case ArchiveFolder_Folder::STATUS_PROGRESS:
                                $actionUri = $this->url(array(
                                        'action' => 'stop',
                                        'id' => $folder->id,
                                    ),
                                    'default');
                                $action = __('Stop');
                                ?>
                        <a href="<?php echo html_escape($actionUri); ?>" class="stop button blue"><?php echo $action; ?></a>

                            <?php
                                $actionUri = $this->url(array(
                                        'action' => 'browse',
                                    ),
                                    'default');
                                $action = __('Refresh page');
                                ?>
                        <a href="<?php echo html_escape($actionUri); ?>" class="refresh button blue"><?php echo $action; ?></a>
                            <?php break;
                            case ArchiveFolder_Folder::STATUS_ADDED:
                            case ArchiveFolder_Folder::STATUS_RESET:
                            case ArchiveFolder_Folder::STATUS_PAUSED:
                            case ArchiveFolder_Folder::STATUS_STOPPED:
                            case ArchiveFolder_Folder::STATUS_KILLED:
                            case ArchiveFolder_Folder::STATUS_COMPLETED:
                            case ArchiveFolder_Folder::STATUS_DELETED:
                            case ArchiveFolder_Folder::STATUS_ERROR:
                            default:

                                 if (is_allowed('ArchiveFolder_Index', 'edit')):
                                    $actionUri = $this->url(array(
                                            'action' => 'check',
                                            'id' => $folder->id,
                                        ),
                                        'default');
                                    $action = __('Check');
                        ?>
                        <a href="<?php echo html_escape($actionUri); ?>" class="check button green"><?php echo $action; ?></a>
                        <?php
                                    $actionUri = $this->url(array(
                                            'action' => 'process',
                                            'id' => $folder->id,
                                        ),
                                        'default');
                                    $action = __('Process');
                        ?>
                        <a href="<?php echo html_escape($actionUri); ?>" class="process button green"><?php echo $action; ?></a>
                        <?php

                                    if (!in_array($folder->status, array(ArchiveFolder_Folder::STATUS_ADDED, ArchiveFolder_Folder::STATUS_COMPLETED))):
                                        $actionUri = $this->url(array(
                                                'action' => 'reset-status',
                                                'id' => $folder->id,
                                            ),
                                            'default');
                                        $action = __('Reset status'); ?>
                        <a href="<?php echo html_escape($actionUri); ?>" class="reset-status button green"><?php echo $action; ?></a>
                                    <?php endif;
                                endif;

                                if (is_allowed('ArchiveFolder_Index', 'delete')):
                                    $actionUri = $this->url(array(
                                            'action' => 'delete-confirm',
                                            'id' => $folder->id,
                                        ),
                                        'default');
                                    $action = __('Delete'); ?>
                        <a href="<?php echo html_escape($actionUri); ?>" class="delete-confirm button red"><?php echo $action; ?></a>
                                <?php endif;

                                break;
                        endswitch;
                    ?>
                    </td>
                    <td><?php echo html_escape(format_date($folder->modified, Zend_Date::DATETIME_SHORT)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="pagination"><?php echo $paginationLinks; ?></div>
    </form>
    <script type="text/javascript">
        Omeka.messages = jQuery.extend(Omeka.messages,
            {'archiveFolder':{
                'confirmation':<?php echo json_encode(__('Are your sure to remove these folders?')); ?>
            }}
        );
        Omeka.addReadyCallback(Omeka.ArchiveFolderBrowse.setupBatchEdit);
    </script>
<?php else: ?>
    <?php if ($totalRecords): ?>
        <p><?php echo __('The query searched %s records and returned no results.', $totalRecords); ?></p>
        <p><a href="<?php echo url('archive-folder/index/browse'); ?>"><?php echo __('See all folders.'); ?></a></p>
    <?php else: ?>
        <p><?php echo __('No url or path have been checked or exposed.'); ?></p>
    <?php endif; ?>
<?php endif; ?>
</div>
<?php
    echo foot();
