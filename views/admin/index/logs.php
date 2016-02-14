<?php echo head(array('title' => __('Archive Folder'))); ?>

<div id="primary">
    <?php echo flash(); ?>

    <?php if (!empty($archiveFolder)): ?>
        <h2><?php echo __('Logs for folder "%s".', $archiveFolder->uri); ?></h2>
        <p><?php echo __('Current status: %s.', '<strong>' . __($archiveFolder->status) . '</strong>'); ?></p>
        <?php

        $messages = $archiveFolder->messages;
        if (!empty($messages)):
            $messages = explode(PHP_EOL, $messages);
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
                <?php foreach ($messages as $message): ?>
                <tr>
                    <td>
                        <?php
                        $date = substr($message, 1, 10);
                        $time = substr($message, 12, 8);
                        echo html_escape(format_date($date, Zend_Date::DATE_SHORT));
                        echo PHP_EOL;
                        echo html_escape(format_date($time, Zend_Date::TIME_MEDIUM));
                        ?>
                    </td>
                    <td>
                        <?php
                            $colon = strpos($message, ':', 22);
                            $priority = substr($message, 22, $colon - 22);
                            echo $priority;
                        ?>
                    </td>
                    <td>
                        <?php
                            echo html_escape(substr($message, $colon + 2));
                        ?>
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
