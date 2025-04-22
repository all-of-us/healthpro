$(document).ready(function () {
    const $form = $('form[name="nph_order_collect"]');
    const $fields = $form.find("input, textarea");
    const $editBtn = $("#order_edit_btn");
    const $editCancelBtn = $("#order_edit_cancel_btn");
    const $resubmitBtn = $("#order_resubmit_btn");
    const $resubmitCancelBtn = $("#order_resubmit_cancel_btn");

    // Initially, disable all form fields and hide the submit/resubmit button
    $fields.prop("disabled", true);
    $resubmitBtn.hide();

    $editBtn.on("click", function () {
        $fields.prop("disabled", false);
        $editBtn.hide();
        $editCancelBtn.hide();
        $resubmitBtn.show();
        $resubmitCancelBtn.show();
    });

    $editCancelBtn.on("click", function () {
        // Clear form data manually
        $form.find('input[type="text"], textarea').val("");
        $form.find('input[type="checkbox"]').prop("checked", false);

        $editBtn.show();
        $editCancelBtn.show();
        $resubmitBtn.hide();
        $resubmitCancelBtn.hide();
    });
});
