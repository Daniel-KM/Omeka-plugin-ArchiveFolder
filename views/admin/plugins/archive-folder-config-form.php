<fieldset id="fieldset-archive-folder"><legend></legend>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('archive_folder_force_update',
                __('Force update for current day')); ?>
        </div>
        <div class="inputs five columns omega">
            <p class="explanation">
                <?php
                echo __('The standard for static repositories requires a date stamp without time.');
                echo ' ' . __('Therefore,  successive updates of the static repository on the same day may not be ingested.');
                echo ' ' . __('If checked, this constraint is bypassed for the internal format "documents".');
                ?>
            </p>
            <?php echo $this->formCheckbox('archive_folder_force_update', true,
                array('checked' => (boolean) get_option('archive_folder_force_update'))); ?>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('archive_folder_memory_limit',
                __('Memory Limit')); ?>
        </div>
        <div class="inputs five columns omega">
            <p class="explanation">
                <?php echo __('The memory limit for the background processes.'); ?>
            </p>
            <?php echo $this->formText('archive_folder_memory_limit', get_option('archive_folder_memory_limit'), null); ?>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('archive_folder_short_dispatcher',
                __('Short Job Dispatcher')); ?>
        </div>
        <div class="inputs five columns omega">
            <p class="explanation">
                <?php
                echo __('Processing a folder and import files is usually a long job.');
                echo ' ' . __("Nevertheless, some configurations don't allow to process them.");
                echo ' ' . __("So, if checked, the short dispatcher will be used, but by default, servers limit them to about 30 seconds.");
                ?>
            </p>
            <?php echo $this->formCheckbox('archive_folder_short_dispatcher', true,
                array('checked' => (boolean) get_option('archive_folder_short_dispatcher'))); ?>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-archive-folder-rights"><legend><?php echo __('Rights and Roles'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('archive_folder_allow_roles', __('Roles that can use Archive Folder')); ?>
        </div>
        <div class="inputs five columns omega">
            <div class="input-block">
                <?php
                    $currentRoles = unserialize(get_option('archive_folder_allow_roles')) ?: array();
                    $userRoles = get_user_roles();
                    echo '<ul>';
                    foreach ($userRoles as $role => $label) {
                        echo '<li>';
                        echo $this->formCheckbox('archive_folder_allow_roles[]', $role,
                            array('checked' => in_array($role, $currentRoles) ? 'checked' : ''));
                        echo $label;
                        echo '</li>';
                    }
                    echo '</ul>';
                ?>
            </div>
        </div>
    </div>
</fieldset>
