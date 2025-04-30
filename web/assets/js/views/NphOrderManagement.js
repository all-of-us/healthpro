$(document).ready(function () {
    document.querySelectorAll(".order-ts").forEach((element) => {
        const originalValue = element.value;
        element.value = "";
        bs5DateTimepicker(element, {
            clock: true,
            sideBySide: true
        });
        element.value = originalValue;
    });

    const $form = $('form[name="nph_admin_order_generation"]');
    const $fields = $form.find("input, textarea");
    const $editBtn = $("#order_edit_btn");
    const $editCancelBtn = $("#order_edit_cancel_btn");
    const $resubmitBtn = $("#order_resubmit_btn");
    const $resubmitCancelBtn = $("#order_resubmit_cancel_btn");
    const $resubmitFinalizeBtn = $("#order_resubmit_finalize_btn");
    const $orderResubmitConfirmationModal = $("#order_resubmit_confirmation_modal");
    const $orderTs = $(".order-ts");

    // Initially, disable all form fields and hide the submit/resubmit button
    $fields.prop("disabled", true);
    $resubmitBtn.hide();

    $editBtn.on("click", function () {
        $fields.each(function () {
            const $field = $(this);
            if ($field.is(":checkbox") || $field.data("sample-cancelled")) {
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
            if ($field.is(":checkbox")) {
                $field.prop("disabled", false);
            }
            if ($field.data("sample-cancelled")) {
                $field.prop("disabled", false);
                $field.prop("readonly", true);
            }
        });
        PMI.hasChanges = false;
        $form.submit();
    });

    $orderTs.on("change", function () {
        PMI.hasChanges = true;
    });

    if ($form.find(".has-error").length) {
        PMI.hasChanges = true;
        $editBtn.trigger("click");
    }
});
