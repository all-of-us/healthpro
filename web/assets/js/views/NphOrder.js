$(document).ready(function () {
    const bootstrapVersion = $(".page-header").data("bs-version") ?? 3;
    if ($("#order-barcode").length === 1) {
        JsBarcode("#order-barcode", $("#order_info").data("order-id"), {
            width: 2,
            height: 50,
            displayValue: true
        });
    }

    const $orderTsSelector = $(".order-ts");
    if ($orderTsSelector.length > 0) {
        const maxDate = new Date();
        maxDate.setHours(23, 59, 59, 999);
        if (bootstrapVersion === 3) {
            $orderTsSelector.pmiDateTimePicker({
                maxDate: maxDate
            });
        } else {
            $orderTsSelector.each(function () {
                bs5DateTimepicker(this, {
                    clock: true,
                    sideBySide: true,
                    useCurrent: true,
                    maxDate: maxDate
                });
            });
        }
    }

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

    $("#nph_order_collect_totalCollectionVolume, #nph_sample_finalize_totalCollectionVolume").on("change", function () {
        let warningMinVolume = $(this).data("warning-min-volume");
        let warningMaxVolume = $(this).data("warning-max-volume");
        if ($(this).val() >= warningMinVolume && $(this).val() <= warningMaxVolume) {
            $("#totalCollectionVolumeWarning").show();
        } else {
            $("#totalCollectionVolumeWarning").hide();
        }
    });

    window.Parsley.addValidator("customDateComparison", {
        validateString: function (value, requirement) {
            let inputDate = new Date(value);
            let comparisonDate = new Date(requirement);
            return inputDate > comparisonDate;
        },
        messages: {
            en: "Time must be after order generation."
        }
    });

    window.Parsley.addValidator("decimalPlaceLimit", {
        validateString: function (value) {
            if (isNaN(value)) {
                return true;
            }
            if (parseInt(value) === parseFloat(value)) {
                return true;
            }
            const totalDecimalPlaces = value.toString().length - value.toString().lastIndexOf(".") - 1;
            return totalDecimalPlaces <= 1;
        }
    });

    $("form[name='nph_order_collect'], form[name='dlw']").parsley({
        errorClass: "has-error",
        classHandler: function (el) {
            return el.$element.closest("td, .col-md-4, .col-md-3");
        },
        errorsContainer: function (el) {
            return el.$element.closest("td, .col-md-4, .col-md-3");
        },
        errorsWrapper: '<div class="help-block"></div>',
        errorTemplate: "<div></div>",
        trigger: "blur"
    });

    $(document).on("dp.hide", ".order-ts", function () {
        $(this).parsley().validate();
    });

    $(document).on("click", "#confirm_btn", function () {
        $("#dlw_participantWeight").parsley().validate();
    });

    $("#collection_notes_help").on("click", function () {
        $("#collection_notes_modal").modal("show");
    });

    $(".toggle-chart-image").on("click", function (e) {
        displayChartImageModal(e);
    });

    const displayChartImageModal = function (e) {
        let image = $(e.currentTarget).data("img");
        let caption = $(e.currentTarget).data("caption");
        let html = "";
        if (image) {
            html += '<img src="' + image + '" class="img-responsive" />';
        }
        if (caption) {
            html += caption;
        }
        this.$("#chartImageModal .modal-body").html(html);
        let chartImageModal = new bootstrap.Modal(this.$("#chartImageModal")[0]);
        chartImageModal.show();
    };
});
