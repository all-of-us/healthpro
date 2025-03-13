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
        $(this).closest(".has-error").removeClass("has-error");
        $(this).siblings(".help-block").children().remove();
        if ($("#order_collectedSamples").find("input:checkbox:checked").length > 0) {
            $("#saliva_tube_change_warning_modal").modal("show");
        } else {
            toggleSalivaTubes($(this));
        }
    });

    $("#saliva_tube_modal_trigger_update").on("click", function () {
        $("#saliva_tube_change_warning_modal").modal("hide");
        $("#order_collectedSamples").find("input:checkbox:checked").prop("checked", false);
        toggleSalivaTubes($("#order_salivaTubeSelection"));
    });

    $("#order_salivaTubeSelection").on("focus", function () {
        $(this).data("previousValue", $(this).val());
    });

    $("#rollback_saliva_selection").on("click", function () {
        $("#saliva_tube_change_warning_modal").modal("hide");
        $("#order_salivaTubeSelection").val($("#order_salivaTubeSelection").data("previousValue"));
    });
    function toggleSalivaTubes() {
        let selectedValue = $("#order_salivaTubeSelection").val();
        if (selectedValue === "0") {
            $("#collectedSamplesFormGroup").hide();
            return;
        }
        let checkboxDiv = $(`input[value="${selectedValue}"]`).parents("div.checkbox");
        checkboxDiv.show();
        checkboxDiv.siblings().hide();
        $("#collectedSamplesFormGroup").show();
        $("#collectedNotesFormGroup").show();
    }

    $("#show_saliva_tube_help_modal").on("click", function () {
        new bootstrap.Modal($("#saliva_tube_help_modal")).show();
    });

    $("input:checkbox").on("change", function () {
        $(this).closest(".has-error").removeClass("has-error");
        $(this).parents("#collectedSamplesFormGroup").children(".help-block").children().remove();
    });
});
