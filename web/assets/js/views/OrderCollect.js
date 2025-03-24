$(document).ready(function () {
    $("#checkall").on("change", function () {
        $("#order_collectedSamples input:checkbox:enabled").prop("checked", $(this).prop("checked"));
    });
    $("#order_collectedSamples input:checkbox").on("change", function () {
        checkAllToggle(this);
    });

    const orderCollectedTs = document.querySelector("#order_collectedTs");

    bs5DateTimepicker(orderCollectedTs, {
        clock: true,
        sideBySide: true,
        useCurrent: true
    });

    new PMI.views["OrderSubPage"]({
        el: $("body")
    });

    let orderVersionSelect = $("#order_orderVersion");
    orderVersionSelect.data("previousValue", orderVersionSelect.val());

    $("#order_orderVersion").on("change", function () {
        let orderForm = $('form[name="order"]');
        if ($(orderForm).find("input:checked").length > 0) {
            new bootstrap.Modal($("#tube_help_modaltube_change_warning_modal")).show();
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
        new bootstrap.Modal($("#tube_help_modal")).show();
    });

    checkAllToggle();

    $("input:checkbox").on("change", function () {
        $(this).closest(".has-error").removeClass("has-error");
        $(this).parents("#collectedSamplesFormGroup").children(".help-block").children().remove();
    });
});
