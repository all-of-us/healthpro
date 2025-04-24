$(document).ready(function () {
    document.querySelectorAll(".order-ts").forEach((element) => {
        bs5DateTimepicker(element, {
            clock: true,
            sideBySide: true,
            useCurrent: true
        });
    });

    const $form = $('form[name="nph_order_collect"]');
    const $fields = $form.find("input, textarea");
    const $editBtn = $("#order_edit_btn");
    const $editCancelBtn = $("#order_edit_cancel_btn");
    const $resubmitBtn = $("#order_resubmit_btn");
    const $resubmitCancelBtn = $("#order_resubmit_cancel_btn");
    const $resubmitFinalizeBtn = $("#order_resubmit_finalize_btn");
    const $orderResubmitConfirmationModal = $("#order_resubmit_confirmation_modal");

    // Initially, disable all form fields and hide the submit/resubmit button
    $fields.prop("disabled", true);
    $resubmitBtn.hide();

    $editBtn.on("click", function () {
        $fields.each(function () {
            const $field = $(this);
            if ($field.is(":checkbox") && $field.is(":checked")) {
                $field.prop("disabled", true);
            } else {
                $field.prop("disabled", false);
            }
        });
        $editBtn.hide();
        $editCancelBtn.hide();
        $resubmitBtn.show();
        $resubmitCancelBtn.show();
    });

    $resubmitBtn.on("click", function () {
        $orderResubmitConfirmationModal.modal("show");
    });

    $resubmitFinalizeBtn.on("click", function () {
        $(this).prop("disabled", true);
        $fields.each(function () {
            const $field = $(this);
            if ($field.is(":checkbox") && $field.is(":checked")) {
                $field.prop("disabled", false);
            }
        });
        PMI.hasChanges = false;
        $form.submit();
    });
});
