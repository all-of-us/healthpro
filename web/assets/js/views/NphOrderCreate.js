$(document).ready(function () {
    let orderCreateSelector = $("#order_create");
    let orderReviewSelector = $("#order_review");

    let addTimePointSamples = function (timePoint, samples) {
        $("#order_review_table tbody").append("<tr><td>" + timePoint + "</td><td>" + samples + "</td></tr>");
    };

    let showPreview = orderCreateSelector.data("show-preview");
    if (showPreview) {
        orderCreateSelector.hide();
        orderReviewSelector.show();
        $("#order_review_table tbody").html("");
        const SAMPLE_STOOL = "STOOL";
        let samples = orderCreateSelector.data("samples");
        let timePoints = orderCreateSelector.data("time-points");
        let nailSamples = orderCreateSelector.data("nail-samples");
        let stoolSamples = orderCreateSelector.data("stool-samples");
        let bloodSamples = orderCreateSelector.data("blood-samples");
        let samplesCount = 0;
        $(".timepoint-samples").each(function () {
            let timePoint = $(this).data("timepoint");
            let nailSubSamples = [];
            let bloodSubSamples = [];
            $(this)
                .find("input:checkbox")
                .each(function () {
                    if ($(this).prop("checked") === true && $(this).prop("disabled") === false) {
                        let sample = $(this).val();
                        if (sample === SAMPLE_STOOL) {
                            let stoolKitSelector = $("#nph_order_stoolKit");
                            if (stoolKitSelector.val()) {
                                let stoolKitSamples = "";
                                stoolSamples.forEach(function (stoolSample) {
                                    let stoolInputSelector = $("#nph_order_" + stoolSample);
                                    if (stoolInputSelector.val()) {
                                        stoolKitSamples +=
                                            ", " + samples[stoolSample] + ": " + stoolInputSelector.val();
                                        samplesCount++;
                                    }
                                });
                                if (stoolKitSamples) {
                                    addTimePointSamples(
                                        timePoints[timePoint],
                                        "Stool: KIT ID " + stoolKitSelector.val() + stoolKitSamples + ""
                                    );
                                }
                            }
                        } else if (nailSamples.includes(sample)) {
                            nailSubSamples.push(samples[$(this).val()]);
                            samplesCount++;
                        } else if (bloodSamples.includes(sample)) {
                            bloodSubSamples.push(samples[$(this).val()]);
                            samplesCount++;
                        } else {
                            addTimePointSamples(timePoints[timePoint], samples[sample]);
                            samplesCount++;
                        }
                    }
                });
            if (nailSubSamples.length > 0) {
                addTimePointSamples(timePoints[timePoint], "Nail: " + nailSubSamples.join(", ") + "");
            }
            if (bloodSubSamples.length > 0) {
                addTimePointSamples(timePoints[timePoint], "Blood: " + bloodSubSamples.join(", ") + "");
            }
        });
        $("#samples_count").html(samplesCount);
    }

    $("#order_review_back_btn").on("click", function () {
        orderCreateSelector.show();
        orderReviewSelector.hide();
    });

    $("#order_generate_btn").on("click", function () {
        $(this).prop("disabled", true);
        let confirmMessage =
            "Are you sure you want to generate orders and print labels? " +
            "This action will officially create the order and sample IDs. " +
            "Click cancel to go back and edit timepoints/samples. " +
            "Click OK to create order(s) and print labels.";
        if (confirm(confirmMessage)) {
            $("#order_create_form").submit();
        } else {
            $(this).prop("disabled", false);
        }
    });

    $("#nph_order_checkAll").on("change", function () {
        $("#order_create_form input:checkbox:enabled").prop("checked", $(this).prop("checked"));
    });

    $(".timepointCheckAll").on("change", function () {
        let timepointSamplesId = "timepoint_samples_" + $(this).data("timepoint");
        $("#" + timepointSamplesId + " input:checkbox:enabled").prop("checked", $(this).prop("checked"));
    });

    let disableEnableStoolFields = function () {
        let stoolCheckboxSel = $(".stool-checkbox");
        if (!stoolCheckboxSel.prop("disabled")) {
            let isStoolBoxChecked = stoolCheckboxSel.prop("checked");
            if (isStoolBoxChecked) {
                $(".stool-text-fields input").prop("disabled", false);
            } else {
                $(".stool-text-fields input").prop("disabled", true).val("");
                $(".stool-text-fields .has-error").removeClass("has-error");
                $(".stool-text-fields span.help-block ul li").remove();
            }
        }
    };

    disableEnableStoolFields();

    $(".stool-checkbox, #timepoint_preLMT, #timepoint_preDSMT, #nph_order_checkAll").on(
        "change",
        disableEnableStoolFields
    );

    if (
        $(".timepoint-samples input:checkbox").length === $(".timepoint-samples input:checkbox:disabled:checked").length
    ) {
        $("#nph_order_validate").hide();
        $("#nph_order_checkAll").prop({
            checked: true,
            disabled: true
        });
    }

    $(".timepoint-samples").each(function () {
        let checked = $(this).find(":checkbox:not(:checked)").length === 0;
        let disabled = $(this).find("input:checkbox").length === $(this).find("input:checkbox:disabled:checked").length;
        $(this).parent().find(".timepointCheckAll").prop({
            checked: checked,
            disabled: disabled
        });
    });

    $("input:checkbox").on("change", function () {
        let allSamplesChecked = $(".timepoint-samples :checkbox:not(:checked):not(:disabled)").length === 0;
        $("#nph_order_checkAll").prop("checked", allSamplesChecked);
        $(".timepoint-samples").each(function () {
            let timePointsChecked = $(this).find(":checkbox:not(:checked):not(:disabled)").length === 0;
            $(this).parent().find(".timepointCheckAll").prop("checked", timePointsChecked);
        });
    });

    $(".sample-disabled-colored").parent().addClass("sample-disabled-colored");

    $("#nph_order_downtime_generated").on("click", function () {
        if ($(this).prop("checked")) {
            $("#downtime-warning-modal").modal("show");
        }
        $(this).prop("checked", false);
    });

    $("#downtime-agree").on("click", function () {
        $("#nph_order_downtime_generated").prop("checked", true);
        $("#downtime-created-ts").show();
        $("#downtime-warning-modal").modal("hide");
    });
    $("#downtime-disagree").on("click", function () {
        $("#nph_order_downtime_generated").prop("checked", false);
        $("#downtime-warning-modal").modal("hide");
    });

    $("#nph_order_createdTs").pmiDateTimePicker();
});
