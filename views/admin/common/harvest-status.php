<?php if ($harvest): ?>
<div class="harvest-status">
    <?php if (empty($asText)): ?>
    <a href="<?php echo url("oaipmh-harvester/index/status?harvest_id={$harvest->id}"); ?>"><?php echo html_escape(ucwords($harvest->status)); ?></a>
    <?php else: ?>
    <span class="status <?php echo $harvest->status == OaipmhHarvester_Harvest::STATUS_IN_PROGRESS ? 'progress' : Inflector::variablize($harvest->status); ?>">
        <?php echo html_escape(__(ucwords($harvest->status))); ?>
    </span>
    <?php endif; ?>
</div>
<?php endif; ?>
