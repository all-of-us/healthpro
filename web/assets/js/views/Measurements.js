$(document).ready(function () {
    let measurement = $("#physicalEvaluation");
    console.log(measurement.data("weight-for-age-lms"));

    new PMI.views["PhysicalEvaluation-" + measurement.data("schema-template")]({
        el: measurement,
        warnings: measurement.data("warnings"),
        conversions: measurement.data("conversions"),
        finalized: measurement.data("finalized"),
        ageInMonths: measurement.data("age-in-months"),
        wightForAgeCharts: measurement.data("weight-for-age-charts"),
        heightForAgeCharts: measurement.data("height-for-age-charts"),
        zScoreCharts: measurement.data("z-score-charts")
    });
    $("#evaluationAffixSave")
        .affix({
            offset: {
                top: 100,
                bottom: $(window).height()
            }
        })
        .width(measurement.width());
});
