<ul class="quick-filter-wrapper">
    <li><a href="#" tabindex="0"><?php echo __('Quick Filter'); ?></a>
    <ul class="dropdown">
        <li><span class="quick-filter-heading"><?php echo __('Quick Filter') ?></span></li>
        <li><a href="<?php echo url('archive-folder/index/browse'); ?>"><?php echo __('View All') ?></a></li>
        <li><a href="<?php echo url('archive-folder/index/browse', array('status' => 'ready')); ?>"><?php echo __('Status Ready'); ?></a></li>
        <li><a href="<?php echo url('archive-folder/index/browse', array('status' => 'processing')); ?>"><?php echo __('Status Processing'); ?></a></li>
        <li><a href="<?php echo url('archive-folder/index/browse', array('status' => 'error')); ?>"><?php echo __('Status Error'); ?></a></li>
    </ul>
    </li>
</ul>
