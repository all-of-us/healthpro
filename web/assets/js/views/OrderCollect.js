$(document).ready(function () {
    $("#checkall").on("change", function () {
        $("#order_collectedSamples input:checkbox:enabled").prop("checked", $(this).prop("checked"));
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

    $("#tube_help_modal_toggle").on("click", function (e) {
        $("#tube_help_modal").modal();
    });
});
