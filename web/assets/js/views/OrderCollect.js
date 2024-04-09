$(document).ready(function () {
    $("#checkall").on("change", function () {
        $("#order_collectedSamples input:checkbox:enabled").prop("checked", $(this).prop("checked"));
    });
    $("#order_collectedTs").pmiDateTimePicker();
    new PMI.views["OrderSubPage"]({
        el: $("body")
    });

    $("#order_orderVersion").on("change", function () {
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
    });
});
