$(document).ready(function () {
    if ($("#order-barcode").length === 1) {
        JsBarcode("#order-barcode", $("#order_info").data("order-id"), {
            width: 2,
            height: 50,
            displayValue: true
        });
    }

    $(".order-ts").pmiDateTimePicker({
        maxDate: new Date().setHours(23, 59, 59, 999)
    });

    $(".toggle-help-image").on("click", function (e) {
        displayHelpModal(e);
    });

    let displayHelpModal = function (e) {
        let image = $(e.currentTarget).data("img");
        let caption = $(e.currentTarget).data("caption");
        let html = "";
        if (image) {
            html += '<img src="' + image + '" class="img-responsive" />';
        }
        if (caption) {
            html += caption;
        }
        this.$("#helpModal .modal-body").html(html);
        this.$("#helpModal").modal();
    };

    $("#scan_barcode").keyup(function () {
        let barcode = $(this).val();
        let sampleFound = false;
        let sampleScanErrorSel = $("#sample_scan_error");
        if (barcode.length === 10) {
            $(".row-samples").each(function () {
                let sampleId = $(this).find("input:checkbox").data("sample-id").toString();
                if (barcode === sampleId) {
                    $(this).find("input:checkbox").prop("checked", true);
                    sampleFound = true;
                    return false;
                }
            });
            if (sampleFound) {
                sampleScanErrorSel.hide();
            } else {
                sampleScanErrorSel.show();
            }
        } else {
            sampleScanErrorSel.hide();
        }
    });
});
