<?php
    $title = __('OAI-PMH Static Repository');
    echo head(array(
        'title' => html_escape($title),
    ));
?>
<div id="primary">
    <?php echo flash(); ?>
    <h2><?php echo $title; ?></h2>
    <p><?php echo $message; ?></p>
</div>
<?php
    echo foot();
?>
