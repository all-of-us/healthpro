$(document).ready(function () {
    $("#sample_finalize_btn").on("click", function (e) {
        e.preventDefault();
        if ($(".sample-finalize-form").parsley().validate()) {
            $("#confirmation_modal").modal("show");
        }
    });

    $("#sample_refinalize_btn").on("click", function (e) {
        $("#confirmation_resubmit_modal").modal("show");
    });

    $("#confirm_finalize_btn").on("click", function () {
        $("#confirmation_modal").modal("hide");
        $("form[name='nph_sample_finalize']").submit();
    });

    $("#confirm_resubmit_btn").on("click", function () {
        $("#confirmation_resubmit_modal").modal("hide");
        $("form[name='nph_sample_resubmit']").submit();
    });

    $(document).on("click", ".add-aliquot-widget", function () {
        if ($(this).data("aliquot-code") === "SALIVAA2") {
            addGlycerolAliquotRow(this);
        } else {
            addNormalAliquotRow(this);
        }
        $(".sample-finalize-form").parsley().validate();
    });

    $(document).on("click", ".delete-aliquot-widget", function () {
        $(this).closest("tr").remove();
    });

    $(document).on("click", ".clear-aliquot-widget", function () {
        if ($(this).closest("tr").attr("class") && $(this).closest("tr").attr("class").includes("SALIVAA2")) {
            $(this).closest("tr").prev().find("input:not(:read-only)").val("");
        }
        $(this).closest("tr").find("input:not(:read-only)").val("");
        $(".sample-finalize-form").parsley().validate();
    });

    $(document).on("keyup", ".aliquot-volume", function () {
        let inputValue = parseFloat($(this).val());
        let minValue = $(this).data("warning-min-volume");
        let maxValue = $(this).data("warning-max-volume");
        if (inputValue && inputValue >= minValue && inputValue <= maxValue) {
            if ($(this).data("warning-target")) {
                $("#" + $(this).data("warning-target")).show();
            }
            $(this).closest("tr").find(".aliquot-volume-warning").show();
        } else {
            if ($(this).data("warning-target")) {
                $("#" + $(this).data("warning-target")).hide();
            }
            $(this).closest("tr").find(".aliquot-volume-warning").hide();
        }
    });

    $(document).on("keyup", ".total-collection-volume", function () {
        let inputValue = parseFloat($(this).val());
        let minValue = $(this).data("warning-min-volume");
        let maxValue = $(this).data("warning-max-volume");
        if (inputValue && inputValue >= minValue && inputValue <= maxValue) {
            $(".total-collection-volume-warning").show();
        } else {
            $(".total-collection-volume-warning").hide();
        }
    });

    $(document).on("keyup", ".glycerol-volume", function () {
        calculateGlycerolVolume(
            $(this).closest("tr").find(".sample"),
            $(this).closest("tr").find(".additive"),
            $(this).closest("tr").data("sample-index")
        );
    });

    $(document).on("keyup", ".aliquot-barcode", function () {
        if (!$(this).prop("readonly")) {
            let barcode = $(this).val();
            let expectedBarcodeLength = $(this).data("barcode-length");
            let expectedBarcodePrefix = $(this).data("barcode-prefix");
            let regex = new RegExp(`^${expectedBarcodePrefix}\\d{${expectedBarcodeLength}}$`);
            let tdSelector = $(this).closest("td");
            let trSelector = $(this).closest("tr");
            let inputSelector = $(this);
            if (regex.test(barcode)) {
                let aliquotTsSelector = $(this).closest("tr").find(".order-ts");
                aliquotTsSelector.focus();
                aliquotTsSelector.data("DateTimePicker").date(new Date());
                aliquotTsSelector.blur();
                $(this).closest("tr").find(".aliquot-volume").focus();
                $.ajax({
                    url: "/nph/ajax/search/aliquot",
                    method: "GET",
                    data: { aliquotId: barcode },
                    success: function (response) {
                        if (response.status === true) {
                            tdSelector.find(".unique-aliquot-error").remove();
                            tdSelector.removeClass("has-error");
                        } else {
                            if (response.type === "aliquot") {
                                showHideUniqueAliquotError(tdSelector, trSelector, inputSelector);
                                let errorMessage =
                                    "<div class='help-block unique-aliquot-error'>Please enter a unique aliquot barcode.</div>";
                                tdSelector.append(errorMessage).addClass("has-error");
                            }
                            if (response.type === "sample") {
                                showHideUniqueAliquotError(tdSelector, trSelector, inputSelector);
                                let errorMessage =
                                    "<tr class='unique-aliquot-error alert alert-warning'><td colspan='4'><i class='fa fa-exclamation-triangle' aria-hidden='true'></i> The matrix ID entered duplicates the collection sample ID. If this was a mistake, please enter the correct matrix ID. If the matrix ID number is the same as the collection sample ID number, continue to aliquot and finalize.</td></tr>";
                                trSelector.after(errorMessage);
                                inputSelector.addClass("input-alert-warning");
                            }
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("Error checking barcode uniqueness:", error);
                    }
                });
            } else {
                showHideUniqueAliquotError(tdSelector, trSelector, inputSelector);
            }
        }
    });

    let showHideUniqueAliquotError = function (tdSelector, trSelector, inputSelector) {
        tdSelector.find(".unique-aliquot-error").remove();
        trSelector.next(".unique-aliquot-error").remove();
        inputSelector.removeClass("input-alert-warning");
    };

    let disableEnableAliquotFields = function () {
        let $checkboxes = $(".sample-cancel-checkbox:checkbox:enabled");
        $checkboxes.each(function () {
            let $row = $(this).closest("tr");
            $row.find(".order-ts, .aliquot-volume").prop("readonly", $(this).is(":checked"));
        });
    };

    let handleSampleCancel = function (element) {
        disableEnableAliquotFields();
        let aliquotTsId = $(element).attr("data-aliquot-ts-id");
        $("#nph_sample_finalize_" + aliquotTsId)
            .parsley()
            .validate();
    };

    function calculateGlycerolVolume(sampleVolumeField, glycerolVolumeField, index) {
        let totalVolumeField = $(`#totalVol${index}`);
        let sampleVolume = $(sampleVolumeField).val() ? parseFloat($(sampleVolumeField).val()) * 1000 : 0;
        let glycerolVolume = $(glycerolVolumeField).val() ? parseFloat($(glycerolVolumeField).val()) : 0;
        let totalVolume = sampleVolume + glycerolVolume;
        let totalVolumeRounded = (totalVolume / 1000).toFixed(2);
        if (totalVolume > totalVolumeField.data("warning-max-volume")) {
            totalVolumeField.val("");
        } else {
            totalVolumeField.val(`${totalVolumeRounded}`);
        }
    }

    function addNormalAliquotRow(element) {
        let list = $($(element).attr("data-list-selector"));
        let aliquotId = list.data("aliquot-id");
        let aliquotUnits = list.data("aliquot-units");
        let counter = list.data("widget-counter");

        // Grab the prototype template and replace the "__name__" used in the id and name of the prototype
        let newCodeWidget = list.data("code-prototype").replace(/__name__/g, counter);
        let newTsWidget = list.data("ts-prototype").replace(/__name__/g, counter);
        let newVolumeWidget = list.data("volume-prototype").replace(/__name__/g, counter);

        // Increment and update widget counter
        counter++;
        list.data("widget-counter", counter);

        let newElem = $(list.attr("data-widget-tags")).html(
            "<td>" +
                newCodeWidget +
                "</td>" +
                '<td style="position: relative">' +
                newTsWidget +
                "</td>" +
                "<td>" +
                newVolumeWidget +
                "</td>" +
                "<td style='position: relative'><span style='position: absolute; bottom: 7px; left: 0;'>" +
                aliquotUnits +
                '</span><i class="fa fa-eraser text-danger clear-aliquot-widget"\' +\n' +
                '                \' style="position: absolute; bottom: 10px; left: 33px; font-size: 22px" role="button"></i></td>'
        );

        $(".aliquots-row-" + aliquotId)
            .last()
            .after(newElem);

        $(".order-ts").pmiDateTimePicker({
            maxDate: new Date().setHours(23, 59, 59, 999)
        });
    }

    function addGlycerolAliquotRow(element) {
        let list = $($(element).attr("data-list-selector"));
        let aliquotId = list.data("aliquot-id");
        let aliquotUnits = list.data("aliquot-units");
        let counter = list.data("widget-counter");
        let aliquotCode = $(element).data("aliquot-code");
        let rows = $(".duplicate-target-" + aliquotId).clone();
        $(rows).find('input[type="checkbox"]').remove();
        rows.each(function () {
            let barcodeName = `nph_sample_finalize[SALIVAA2][${counter}]`;
            let tsName = `nph_sample_finalize[SALIVAA2AliquotTs][${counter}]`;
            let volumeName = `nph_sample_finalize[SALIVAA2Volume][${counter}]`;
            let glycerolVolumeName = `nph_sample_finalize[SALIVAA2glycerolAdditiveVolume][${counter}]`;
            let targetName = `SALIVAA2-warning-target${counter}`;
            let glycerolTarget = `SALIVAA2-warning-target-glycerol${counter}`;
            let totalTarget = `SALIVAA2-warning-target-total${counter}`;
            let barcodeId = `nph_sample_finalize_SALIVAA2_${counter}`;
            let tsId = `nph_sample_finalize_SALIVAA2AliquotTs_${counter}`;
            let volumeId = `nph_sample_finalize_SALIVAA2Volume_${counter}`;
            let glycerolId = `nph_sample_finalize_SALIVAA2glycerolAdditiveVolume_${counter}`;
            $(this).removeClass("duplicate-target-" + aliquotCode);
            $(this).find("[name='nph_sample_finalize[SALIVAA2][0]']").attr({
                name: barcodeName,
                id: barcodeId
            });
            $(this).find("[name='nph_sample_finalize[SALIVAA2AliquotTs][0]']").attr({
                name: tsName,
                id: tsId
            });
            $(this).find("[name='nph_sample_finalize[SALIVAA2Volume][0]']").attr({
                name: volumeName,
                id: volumeId,
                "data-warning-target": targetName
            });
            $(this).find("[name='nph_sample_finalize[SALIVAA2glycerolAdditiveVolume][0]']").attr({
                name: glycerolVolumeName,
                id: glycerolId,
                "data-warning-target": glycerolTarget
            });
            $(this).find("#SALIVAA2-warning-target0").attr("id", targetName);
            $(this).find("#SALIVAA2-warning-target-glycerol0").attr("id", glycerolTarget);
            $(this).find("#SALIVAA2-warning-target-total0").attr("id", totalTarget);
            $(this).find("");
            $(this).find("input").val("");
            $(this).find(".text-warning").hide();
            $(this).find(".help-block").remove();
            $(this).find("input:not(.totalVol)").attr("readonly", false);
        });
        counter++;
        list.data("widget-counter", counter);
        $(element).closest("tr").before(rows);
        $(".order-ts").pmiDateTimePicker({
            maxDate: new Date().setHours(23, 59, 59, 999)
        });
    }

    $(".aliquot-ts, .freeze-ts").on("dp.change", function (event) {
        let collectedTsSelector = $('input[id*="CollectedTs"]');
        let orderCollectedTs = new Date(collectedTsSelector.val());
        let fieldTs = new Date($(this).val());
        let fieldType = $(this).data("field-type");
        let differenceCheck = 2;
        if (fieldType === "freeze") {
            differenceCheck = 72;
        }
        let difference = Math.abs(fieldTs.getTime() - orderCollectedTs.getTime()) / (60 * 60 * 1000);
        if (difference > differenceCheck) {
            $(this).addClass("date-range-warning");
            collectedTsSelector.addClass("date-range-warning");
            $("#" + fieldType + "TimeWarning").show();
        } else {
            $(this).removeClass("date-range-warning");
            collectedTsSelector.removeClass("date-range-warning");
            if ($("td.has-warning>input.order-ts").length === 0) {
                $("#" + fieldType + "TimeWarning").hide();
            }
        }
        clearServerErrors(event);
    });

    disableEnableAliquotFields();

    $(".aliquot-volume").trigger("keyup");

    $(".sample-cancel-checkbox").on("change", function () {
        handleSampleCancel(this);
    });

    const dateComparison = (value, requirement, parsleyFieldInstance) => {
        if (parsleyFieldInstance.$element.is(":disabled") || parsleyFieldInstance.$element.is("[readonly]")) {
            return true;
        }
        const inputDate = new Date(value);
        const collectedTs = document.getElementById(requirement).value;
        if (collectedTs) {
            const comparisonDate = new Date(collectedTs);
            return inputDate > comparisonDate;
        }
        return true;
    };

    window.Parsley.addValidator("aliquotDateComparison", {
        validateString: dateComparison,
        messages: {
            en: "Aliquot time must be after collection time."
        }
    });

    window.Parsley.addValidator("freezeDateComparison", {
        validateString: dateComparison,
        messages: {
            en: "Freeze time must be after collection time."
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

    $(".sample-finalize-form").parsley({
        errorClass: "has-error",
        classHandler: function (el) {
            return el.$element.closest(".form-group, td, .input-group");
        },
        errorsContainer: function (el) {
            let errorContainer = el.$element.closest("tr").next().find("td.has-error");
            if (errorContainer.length > 0) {
                return errorContainer;
            } else {
                return el.$element.closest(".form-group, td");
            }
        },
        errorsWrapper: '<div class="help-block"></div>',
        errorTemplate: "<div></div>",
        trigger: "blur"
    });

    $(document).on("dp.hide", ".order-ts", function () {
        $(this).parsley().validate();
    });

    const clearServerErrors = (e) => {
        $(e.currentTarget).next("span.help-block").remove();
    };

    $(".sample-finalize-form input").on("change", clearServerErrors);
});
