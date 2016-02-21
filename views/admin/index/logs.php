<?php echo head(array('title' => __('Archive Folder'))); ?>

<div id="primary">
    <?php echo flash(); ?>

    <?php if (!empty($archiveFolder)): ?>
        <h2><?php echo __('Logs for folder "%s".', $archiveFolder->uri); ?></h2>
        <p><?php echo __('Current status: %s.', '<strong>' . __($archiveFolder->status) . '</strong>'); ?></p>
        <?php

        $messages = $archiveFolder->messages;
        if (!empty($messages)):
            $dateTimePattern = '(^\[[1-2][0-9]{3}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[1-2][0-9]|3[0-1]) (?:[0-1][0-9]|2[0-3]):(?:[0-5][0-9]):(?:[0-5][0-9])\]) ';
            $priorityPattern = '(.*?): ';
            $messages = preg_split('/' . $dateTimePattern . $priorityPattern . '/m', $messages, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            ?>
        <table class="simple">
            <thead>
                <tr>
                    <th><?php echo __('Time'); ?></th>
                    <th><?php echo __('Type'); ?></th>
                    <th><?php echo __('Message'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messages as $i =>$message):
                    if ($i % 3) continue;
                    $date = substr($message, 1, 10);
                    $time = substr($message, 12, 8);
                    $priority = $messages[$i + 1];
                    $message = trim($messages[$i + 2]);
                ?>
                <tr>
                    <td><?php
                        echo html_escape(format_date($date, Zend_Date::DATE_SHORT));
                        echo ' ';
                        echo html_escape(format_date($time, Zend_Date::TIME_MEDIUM));
                    ?></td>
                    <td><?php echo $priority; ?>
                    </td>
                    <td><?php echo html_escape($message); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p><?php echo __('This archive folder has no message yet.'); ?></p>
        <?php endif; ?>
    <?php else: ?>
        <p><?php echo __('This is not an archive folder.'); ?></p>
    <?php endif; ?>
    <p><?php echo __('Back to %sArchive Folder%s.',
        '<a href="' . ADMIN_BASE_URL . '/archive-folder">', '</a>'); ?></p>
    </p>
</div>

<?php
    echo foot();
