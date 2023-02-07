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
        let prePostTimePoints = ["preLMT", "postLMT"];
        let samplesCount = 0;
        $(".timepoint-samples").each(function () {
            let timePoint = $(this).data("timepoint");
            if (prePostTimePoints.includes(timePoint)) {
                let nailSubSamples = [];
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
                            } else {
                                addTimePointSamples(timePoints[timePoint], samples[sample]);
                                samplesCount++;
                            }
                        }
                    });
                if (nailSubSamples.length > 0) {
                    addTimePointSamples(timePoints[timePoint], "Nail: " + nailSubSamples.join(", ") + "");
                }
            } else {
                let bloodSamples = [];
                $(this)
                    .find("input:checkbox")
                    .each(function () {
                        if ($(this).prop("checked") === true && $(this).prop("disabled") === false) {
                            bloodSamples.push(samples[$(this).val()]);
                            samplesCount++;
                        }
                    });
                if (bloodSamples.length > 0) {
                    bloodSamples = bloodSamples.join(", ");
                    addTimePointSamples(timePoints[timePoint], "Blood: " + bloodSamples);
                }
            }
        });
        $("#samples_count").html(samplesCount);
    }

    $("#order_review_back_btn").on("click", function () {
        orderCreateSelector.show();
        orderReviewSelector.hide();
    });

    $("#order_generate_btn").on("click", function () {
        let confirmMessage =
            "Are you sure you want to generate orders and print labels? " +
            "This action will officially create the order and sample IDs. " +
            "Click cancel to go back and edit timepoints/samples." +
            "Click OK to create order(s) and print labels.";
        if (confirm(confirmMessage)) {
            $("#order_create_form").submit();
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
            }
        }
    };

    disableEnableStoolFields();

    $(".stool-checkbox, #timepoint_preLMT, #nph_order_checkAll").on("change", disableEnableStoolFields);

    if ($(".timepoint-samples :checkbox:not(:checked)").length === 0) {
        $("#nph_order_validate").hide();
    } else {
        $("#nph_order_validate").show();
    }
});
