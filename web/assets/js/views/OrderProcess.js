$(document).ready(function () {
    let order = $("#order_process");
    if (order.data("order-type") === "saliva") {
        $("input.sample-disabled").closest("label").addClass("text-warning");
        $("input.sample-disabled")
            .closest("div.checkbox")
            .append('<small class="text-muted">was not collected</small>');
        $("#order_processedSamples input").each(function () {
            if (!order.data("order-finalized")) {
                if ($(this).attr("disabled")) {
                    return;
                }
            }
            var checkBoxDiv = $(this).closest("div.checkbox");
            var sample = $(this).val();
            $("#order_processedSamplesTs_" + sample)
                .closest(".form-group")
                .detach()
                .appendTo(checkBoxDiv)
                .css("margin", "5px 0 15px 20px");
            let orderProcessedTs = document.querySelector("#order_processedSamplesTs_" + sample);
            bs5DateTimepicker(orderProcessedTs, {
                clock: true,
                sideBySide: true,
                useCurrent: true
            });
            $("#order_processedSamplesTs_" + sample).addClass("input-sm");

            // Display processed sample time error messages
            if ($('form[name="order"] .alert-danger').length == 0) {
                var error = $(this).attr("error");
                if (typeof error !== "undefined") {
                    $('input[name="order[processedSamplesTs][' + sample + ']"]').after(
                        '<span class="text-danger"><i class="fa fa-exclamation-circle text-danger" aria-hidden="true"></i> ' +
                            error +
                            "</span>"
                    );
                }
            }
        });
        $("#order_processedSamplesTs").remove();
    } else {
        $(".sample-disabled label").addClass("text-warning");
        $(".sample-disabled")
            .find("td:last")
            .append('<small style="margin-left:15px" class="text-muted">was not collected</small>');
        $("#order_processedSamples input").each(function () {
            if (!order.data("order-finalized")) {
                if ($(this).attr("disabled")) {
                    return;
                }
            }
            var checkBoxTr = $(this).closest("tr");
            var timeTd = $('<td colspan="5">');
            var timeTr = $("<tr><td></td></tr>");
            var sample = $(this).val();
            $("#order_processedSamplesTs_" + sample)
                .closest(".form-group")
                .detach()
                .css("margin-bottom", "10px")
                .appendTo(timeTd);
            timeTr.append(timeTd);
            checkBoxTr.after(timeTr);
            let orderProcessedTs = document.querySelector("#order_processedSamplesTs_" + sample);
            bs5DateTimepicker(orderProcessedTs, {
                clock: true,
                sideBySide: true,
                useCurrent: true
            });
            $("#order_processedSamplesTs_" + sample).addClass("form-control-sm");
        });
        $("#order_processedSamplesTs").remove();

        // Display processed samples time error/warning messages
        if ($('form[name="order"] .alert-danger').length == 0) {
            $(".samples").each(function (e) {
                var error = $(this).find('input[name="order[processedSamples][]"]').attr("error");
                var warning = $(this).find('input[name="order[processedSamples][]"]').attr("warning");
                if (typeof error !== "undefined") {
                    $(this)
                        .next()
                        .find(":text")
                        .after(
                            '<span class="text-danger"><i class="fa fa-exclamation-circle text-danger" aria-hidden="true"></i> ' +
                                error +
                                "</span>"
                        );
                } else if (typeof warning !== "undefined") {
                    $(this)
                        .next()
                        .find(":text")
                        .after(
                            '<span class="text-warning"><i class="fa fa-exclamation-triangle text-warning" aria-hidden="true"></i> ' +
                                warning +
                                "</span>"
                        );
                }
            });
        }
    }

    new PMI.views["OrderSubPage"]({
        el: $("body")
    });

    $('.process-form button[type="submit"]').on("click", function () {
        var message = "";
        $('input[name="order[processedSamples][]"]').each(function () {
            //Select samples that are unchecked and not disabled
            if ($(this).prop("checked") === false && $(this).prop("disabled") === false) {
                message =
                    "Warning: At least one sample that was collected was not processed. Are you sure you wish to continue?";
                return false;
            }
        });
        if (message) {
            return confirm(message);
        }
    });
});
