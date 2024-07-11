$(document).ready(function () {
    $("#checkall").on("change", function () {
        $("#order_collectedSamples input:checkbox:enabled").prop("checked", $(this).prop("checked"));
    });
    $("#order_collectedSamples input:checkbox").on("change", function () {
        checkAllToggle(this);
    });
    $("#order_collectedTs").pmiDateTimePicker();
    new PMI.views["OrderSubPage"]({
        el: $("body")
    });

    let orderVersionSelect = $("#order_orderVersion");
    orderVersionSelect.data("previousValue", orderVersionSelect.val());

    $("#order_orderVersion").on("change", function () {
        let orderForm = $('form[name="order"]');
        if ($(orderForm).find("input:checked").length > 0) {
            $("#tube_change_warning_modal").modal();
        } else {
            TriggerTubeUpdate();
        }
    });

    $("#modal_trigger_update").on("click", function () {
        TriggerTubeUpdate();
    });

    $("#tube_change_warning_modal").on("hidden.bs.modal", function () {
        orderVersionSelect.val(orderVersionSelect.data("previousValue"));
    });

    function TriggerTubeUpdate() {
        let orderForm = $('form[name="order"]');
        $('<input name="updateTubes" type="hidden" value="true">').appendTo(orderForm);
        window.addEventListener("beforeunload", function (event) {
            event.stopImmediatePropagation();
        });
        window.addEventListener("unload", function (event) {
            event.stopImmediatePropagation();
        });
        PMI.disabledUnsavedPrompt();
        orderForm.attr("");
        orderForm.trigger("submit");
    }

    function checkAllToggle(element) {
        let allCheckboxesNotCheckall = $("#order_collectedSamples input:checkbox:enabled").not("#checkall");
        if (element !== undefined && !$(element).prop("checked")) {
            $("#checkall").prop("checked", false);
        } else if (allCheckboxesNotCheckall.filter(":checked").length === allCheckboxesNotCheckall.length) {
            $("#checkall").prop("checked", true);
        }
    }
    $("#tube_help_modal_toggle").on("click", function (e) {
        $("#tube_help_modal").modal();
    });

    checkAllToggle();
    toggleSalivaTubes($("#order_salivaTubeSelection"));
    if ($("#tubesChanged").length > 0) {
        PMI.enableUnsavedPrompt();
        PMI.hasChanges = true;
    }

    $("#order_salivaTubeSelection").on("change", function () {
        toggleSalivaTubes($(this));
        $("#saliva_tube_change_warning_modal").modal();
    });

    $("#salive_tube_modal_trigger_update").on("click", function () {
        $("#saliva_tube_change_warning_modal").modal("hide");
        TriggerTubeUpdate();
    });

    function toggleSalivaTubes() {
        let selectedValue = $("#order_salivaTubeSelection").val();
        let checkboxDiv = $(`input[value="${selectedValue}"]`).parents("div.checkbox");
        checkboxDiv.show();
        checkboxDiv.siblings().hide();
        $("#collectedSamplesFormGroup").show();
        $("#collectedNotesFormGroup").show();
    }
});
