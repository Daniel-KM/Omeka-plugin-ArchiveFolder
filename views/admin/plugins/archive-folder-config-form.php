<fieldset id="fieldset-archive-folder-process"><legend><?php echo __('Process'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('archive_folder_processor',
                __('Command of the processor')); ?>
        </div>
        <div class="inputs five columns omega">
            <p class="explanation">
                <?php echo __('Command of the processor. Let empty to use the internal xslt processor of php.'); ?>
                <?php echo __('This is required by some formats that need to parse a xslt 2 stylesheet.'); ?>
                <?php echo __('See format of the command and examples in the readme.'); ?>
            </p>
            <?php echo get_view()->formText('archive_folder_processor', get_option('archive_folder_processor'), null); ?>
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
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('archive_folder_slow_process', __('Slow the process')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formText('archive_folder_slow_process', get_option('archive_folder_slow_process'), null); ?>
            <p class="explanation">
                <?php echo __('Some providers check if too many files are uploaded in one shot and prevent the import.'); ?>
                <?php echo __('This option sleeps the process during this number of seconds to avoid such a limit.'); ?>
            </p>
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
</fieldset>
<fieldset id="fieldset-archive-folder-rights"><legend><?php echo __('Rights and Roles'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('archive_folder_allow_roles', __('Roles that can use Archive Folder')); ?>
        </div>
        <div class="inputs five columns omega">
            <div class="input-block">
                <ul style="list-style-type: none;">
                <?php
                    $currentRoles = unserialize(get_option('archive_folder_allow_roles')) ?: array();
                    $userRoles = get_user_roles();
                    foreach ($userRoles as $role => $label) {
                        echo '<li>';
                        echo $this->formCheckbox('archive_folder_allow_roles[]', $role,
                            array('checked' => in_array($role, $currentRoles) ? 'checked' : ''));
                        echo $label;
                        echo '</li>';
                    }
                ?>
                </ul>
            </div>
        </div>
    </div>
</fieldset>
