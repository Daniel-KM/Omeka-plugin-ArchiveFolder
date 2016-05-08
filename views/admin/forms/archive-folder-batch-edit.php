<fieldset id="archivefolder-item-metadata">
    <h2><?php echo __('Archive Folder'); ?></h2>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('orderByFilename',
                __('Order files')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formCheckbox('custom[archivefolder][orderByFilename]', null, array(
                'checked' => false, 'class' => 'order-by-filename-checkbox')); ?>
            <p class="explanation">
                <?php echo __('Order files of each item by their original filename.'); ?></p>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('mixImages',
                __('Mix images and other files')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formCheckbox('custom[archivefolder][mixImages]', null, array(
                'checked' => false, 'class' => 'mix-images-checkbox')); ?>
            <p class="explanation">
                <?php echo __('If checked, types will be mixed, else images will be ordered before other files.'); ?>
            </p>
        </div>
    </div>
</fieldset>
