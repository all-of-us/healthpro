$(document).ready(function () {
    var currentStep = $("#currentStep").data("current-step");
    if ($(".finalize-form .alert-danger").length !== 0) {
        currentStep = "finalize";
    }
    // Switch tab to active step
    let targetSelector = ".nav-tabs button[data-bs-target='#" + currentStep + "']";
    let tabTriggerEl = $(targetSelector)[0];
    if (tabTriggerEl) {
        let tab = new bootstrap.Tab(tabTriggerEl);
        tab.show();
    }

    $("#checkall").on("change", function () {
        $("#biobank_order_finalizedSamples input:checkbox:enabled").prop("checked", $(this).prop("checked"));
    });

    $('.finalize-form button[type="submit"]').on("click", function () {
        //Display warning message
        var message = "Are you sure you want to finalize this order?";
        var collectedSamples = $("#collectedSamples").data("collected-samples");
        $('input[name="biobank_order[finalizedSamples][]"]').each(function () {
            //Select samples that are unchecked and not disabled
            if (
                $(this).prop("checked") === false &&
                collectedSamples &&
                $.inArray($(this).val(), collectedSamples) !== -1
            ) {
                message =
                    "Warning: At least one sample that was collected and processed (as applicable) was not finalized. Are you sure you wish to continue?";
                return false;
            }
        });
        return confirm(message);
    });

    new PMI.views["OrderSubPage"]({
        el: $("body")
    });
});
