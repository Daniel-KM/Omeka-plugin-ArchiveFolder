<?php
$pageTitle = __('Add a new folder to archive');
echo head(array(
    'title' => $pageTitle,
    'bodyclass' => 'archive-folder edit',
));
?>
<div id="primary">
    <?php
        echo flash();
    ?>
    <p>
        <?php echo __('Fill infos about the folder to prepare.'); ?>
    </p>
    <ul>
        <li>
            <?php echo __('Don\'t forget to allow used extensions and mime-types in %sparameters%s, in particular "xml".', '<a href="' . url('/settings/edit-security') . '">', '</a>'); ?>
        </li>
        <li>
            <?php echo __('Currently, these parameters are not editable once saved, but they can be deleted and rebuilt easily.'); ?>
        </li>
    </ul>
    <?php
        echo $this->form;
        echo $this->csrf;
    ?>
</div>
<?php
echo foot();
