$(document).ready(function () {
    var order = $("#order_finalize");
    var orderType = order.data("order-type");
    var orderCollectTime = order.data("order-collect-time");
    var orderSite = order.data("order-site");
    var userSite = order.data("user-site");
    var name = 'input[name="order[finalizedSamples][]"]';
    var sampleWarning = "At least one sample that was collected and processed (as applicable) was not finalized. ";
    if (orderType === "saliva") {
        sampleWarning = "At least one sample that was collected (as applicable) was not finalized. ";
        $("input.sample-disabled").closest("label").addClass("text-warning");
        var html;
        var collected = $(name).attr("collected");
        if (typeof collected !== "undefined") {
            html =
                '<label><b>Collected:</b></label> <i class="fa fa-check text-success" aria-hidden="true"></i> ' +
                collected +
                "</span>";
        } else {
            html =
                '<label><b>Collected:</b></label> <span class="label-normal text-danger">Not collected</span></span>';
        }
        $(name)
            .closest("div.checkbox")
            .append('<span class="saliva-info">' + html + "</span>");
    } else {
        $(".sample-disabled label").addClass("text-warning");
        $(".header-collected").append("<br><small>" + orderCollectTime + "</small>");
        $(".samples").each(function (e) {
            var html;
            var collected = $(this).find(name).attr("collected");
            var processed = $(this).find(name).attr("processed");
            var requiredProcessing = $(this).find(name).attr("required-processing");
            var warning = $(this).find(name).attr("warning");
            var error = $(this).find(name).attr("error");
            if (typeof collected !== "undefined") {
                html = '<td align="center"><i class="fa fa-check text-success" aria-hidden="true"></i></td>';
            } else {
                html = '<td align="center"><label class="label-normal text-warning">Not collected</label></td>';
            }
            if (typeof processed !== "undefined") {
                if (typeof error !== "undefined") {
                    html +=
                        '<td><span class="text-danger"><i class="fa fa-exclamation-circle" aria-hidden="true" data-toggle="tooltip" data-placement="bottom" title="' +
                        error +
                        '"></i> ' +
                        processed +
                        "</span></td>";
                } else if (typeof warning !== "undefined") {
                    html +=
                        '<td><span class="text-warning"><i class="fa fa-exclamation-triangle" aria-hidden="true" data-toggle="tooltip" data-placement="bottom" title="' +
                        warning +
                        '"></i> ' +
                        processed +
                        "</span></td>";
                } else {
                    html += '<td><i class="fa fa-check text-success" aria-hidden="true"></i> ' + processed + "</td>";
                }
            } else {
                if (typeof requiredProcessing !== "undefined") {
                    html += '<td><label class="label-normal text-warning">Not processed</label></td>';
                }
            }
            $(this).find("td:last").after(html);
        });
    }

    $('[data-toggle="tooltip"]').tooltip();

    $("#checkall").on("change", function () {
        $("#order_finalizedSamples input:checkbox:enabled").prop("checked", $(this).prop("checked"));
        showHideConfirmEmptyOrderCheck();
    });

    $("#order_finalizedSamples input:checkbox").on("change", function () {
        checkAllToggle(this);
    });

    function checkAllToggle(element) {
        let allCheckboxesNotCheckall = $("#order_finalizedSamples input:checkbox:enabled").not("#checkall");
        if (element !== undefined && !$(element).prop("checked")) {
            $("#checkall").prop("checked", false);
        } else if (allCheckboxesNotCheckall.filter(":checked").length === allCheckboxesNotCheckall.length) {
            $("#checkall").prop("checked", true);
        }
    }

    let orderFinalizeTss = document.querySelector("#order_finalizedTs");
    bs5DateTimepicker(orderFinalizeTss, {
        clock: true,
        sideBySide: true,
        useCurrent: true
    });

    new PMI.views["OrderSubPage"]({
        el: $("body")
    });

    $("#enable-number").on("click", function () {
        $("#enable-number").addClass("active");
        $("#enable-barcode").removeClass("active");
        $("#fedex-barcode").hide();
        $("#fedex-number").removeClass("col-6").addClass("col-12");
        $("#fedex-number input").attr("readonly", false);
        return false;
    });
    $("#enable-barcode").on("click", function () {
        $("#enable-barcode").addClass("active");
        $("#enable-number").removeClass("active");
        $("#fedex-barcode").show();
        $("#fedex-number").removeClass("col-12").addClass("col-6");
        $("#fedex-number input").attr("readonly", true);
        return false;
    });

    $("#fedex_barcode_first, #fedex_barcode_second").on("change keyup", function () {
        var target;
        if ($(this).attr("id") === "fedex_barcode_second") {
            target = $("#order_fedexTracking_second");
        } else {
            target = $("#order_fedexTracking_first");
        }
        target.attr("placeholder", "");
        target.parent().removeClass("has-error").removeClass("has-success");
        var barcode = $(this).val().trim();
        if (barcode.match(/^[a-zA-Z0-9]{18}$/)) {
            // 18 digit alphanumeric barcode for UPS
            target.val(barcode);
            target.parent().addClass("has-success");
        } else if (barcode.match(/^[0-9]{34}$/)) {
            // 34 digit barcode for FedEx
            var fedexTracking = barcode.substring(20);
            fedexTracking = fedexTracking.replace(/^0{1,2}/, ""); // trim up to two leading 0's
            target.val(fedexTracking);
            target.parent().addClass("has-success");
        } else {
            target.val("");
            if (barcode) {
                target.attr("placeholder", "Invalid barcode");
                target.parent().addClass("has-error");
            }
        }
    });

    $('.finalize-form button[type="submit"]').on("click", function () {
        //Display warning message
        var confirmMessage = "Are you sure you want to finalize this order?";
        var message = confirmMessage;
        var siteWarning;
        if (userSite !== orderSite) {
            siteWarning = "The order creation site and finalization site do not match. ";
            message = "Warning! " + siteWarning + confirmMessage;
        }
        $('input[name="order[finalizedSamples][]"]').each(function () {
            //Select samples that are unchecked and not disabled
            if ($(this).prop("checked") === false && $(this).prop("disabled") === false) {
                message = "Warning! " + sampleWarning + confirmMessage;
                if (typeof siteWarning !== "undefined" && siteWarning) {
                    message = "Warning! 1) " + siteWarning + "2) " + sampleWarning + confirmMessage;
                }
                return false;
            }
        });
        return confirm(message);
    });

    let handleShippingFields = function () {
        if ($('input:radio[name="order[sampleShippingMethod]"]').is(":checked")) {
            let sampleShippingMethod = $('input:radio[name="order[sampleShippingMethod]"]:checked').val();
            if (sampleShippingMethod === "fedex") {
                $("#shipping_fields").show();
                $("#courier_warning").hide();
            } else if (sampleShippingMethod === "courier") {
                $("#shipping_fields").hide();
                $("#courier_warning").show();
            }
        }
    };

    $("#order_sampleShippingMethod input[type=radio]").on("change", function () {
        handleShippingFields();
    });

    handleShippingFields();

    $("#toggleShippingHelpModal").on("click", function () {
        $("#shipping_method_help_modal").modal();
    });
});
