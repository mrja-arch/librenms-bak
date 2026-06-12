<?php
/*
 * LibreNMS
 *
 * Copyright (c) 2014 Neil Lathwood <https://github.com/laf/ http://www.lathwood.co.uk/fa>
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */

?>

<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="delete-alert-rule-title" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h5 class="modal-title" id="delete-alert-rule-title"><?= __('Delete Alert Rule') ?></h5>
            </div>
            <div class="modal-body">
                <p id="delete-alert-rule-message"><?= __('Click Delete to remove this alert rule.') ?></p>
            </div>
            <div class="modal-footer">
                <form role="form" class="remove_token_form">
                    <?php echo csrf_field() ?>
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?= __('Cancel') ?></button>
                    <button type="submit" class="btn btn-danger danger" id="alert-rule-removal" data-target="alert-rule-removal"><?= __('Delete') ?></button>
                    <input type="hidden" name="alert_id" id="alert_id" value="">
                    <input type="hidden" name="alert_name" id="alert_name" value="">
                    <input type="hidden" name="confirm" id="confirm" value="yes">
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$('#confirm-delete').on('show.bs.modal', function(event) {
    alert_id = $(event.relatedTarget).data('alert_id');
    alert_name = $(event.relatedTarget).data('alert_name');
    $("#alert_id").val(alert_id);
    $("#alert_name").val(alert_name);
    var deleteMessage = <?= json_encode(__('Click Delete to remove alert rule ":name".')) ?>;
    $("#delete-alert-rule-message").text(deleteMessage.replace(':name', alert_name));
});

$('#alert-rule-removal').on('click', function(event) {
    event.preventDefault();
    var alert_id = $("#alert_id").val();
    var alert_name = $("#alert_name").val();
    $.ajax({
        type: 'DELETE',
        url: '<?php echo route('alert-rule.destroy', ':alert_id') ?>'.replace(':alert_id', alert_id),
        success: function(msg) {
            if(msg.status === 200) {
                $("#rule_id_"+alert_id).remove();
            } else {
                toastr.error(<?= json_encode(__('Failed to Delete Alert Rule')) ?>);
            }
            $("#confirm-delete").modal('hide');
        },
        error: function() {
            toastr.error(<?= json_encode(__('Failed to Delete Alert Rule')) ?>);
            $("#confirm-delete").modal('hide');
        }
    });
});
</script>
