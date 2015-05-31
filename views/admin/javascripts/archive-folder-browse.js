if (!Omeka) {
    var Omeka = {};
}

Omeka.ArchiveFolderBrowse = {};

(function ($) {

    Omeka.ArchiveFolderBrowse.setupBatchEdit = function () {
        var archiveFolderCheckboxes = $("table#archive-folders tbody input[type=checkbox]");
        var globalCheckbox = $('th.batch-edit-heading').html('<input type="checkbox">').find('input');
        var batchEditSubmit = $('.batch-edit-option input');
        /**
         * Disable the batch submit button first, will be enabled once records
         * checkboxes are checked.
         */
        batchEditSubmit.prop('disabled', true);

        /**
         * Check all the archiveFolderCheckboxes if the globalCheckbox is checked.
         */
        globalCheckbox.change(function() {
            archiveFolderCheckboxes.prop('checked', !!this.checked);
            checkBatchEditSubmitButton();
        });

        /**
         * Uncheck the global checkbox if any of the archiveFolderCheckboxes are
         * unchecked.
         */
        archiveFolderCheckboxes.change(function(){
            if (!this.checked) {
                globalCheckbox.prop('checked', false);
            }
            checkBatchEditSubmitButton();
        });

        /**
         * Check whether the batchEditSubmit button should be enabled.
         * If any of the archiveFolderCheckboxes is checked, the batchEditSubmit button
         * is enabled.
         */
        function checkBatchEditSubmitButton() {
            var checked = false;
            archiveFolderCheckboxes.each(function() {
                if (this.checked) {
                    checked = true;
                    return false;
                }
            });

            batchEditSubmit.prop('disabled', !checked);
        }
    };

    $(document).ready(function() {
        // Delete a simple record.
        $('.archive-folder input[name="submit-batch-delete"]').click(function(event) {
            event.preventDefault();
            if (!confirm(Omeka.messages.archiveFolder.confirmation)) {
                return;
            }
            $('table#archive-folders thead tr th.batch-edit-heading input').attr('checked', false);
            $('.batch-edit-option input').prop('disabled', true);
            $('table#archive-folders tbody input[type=checkbox]:checked').each(function(){
                var checkbox = $(this);
                var row = $(this).closest('tr.archive-folder');
                var current = $('#archive-folder-' + this.value);
                var ajaxUrl = current.attr('href') + '/archive-folder/ajax/delete';
                checkbox.addClass('transmit');
                $.post(ajaxUrl,
                    {
                        id: this.value
                    },
                    function(data) {
                        row.remove();
                    }
                );
            });
        });

        // Toggle details for the current row.
        $('.archive-folder-details').click(function (event) {
            event.preventDefault();
            $(this).closest('td').find('.details').slideToggle('fast');
        });
    });

})(jQuery);
