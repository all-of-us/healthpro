$(document).ready(function () {
    let measurement = $("#physicalEvaluation");

    new PMI.views["PhysicalEvaluation-" + measurement.data("schema-template")]({
        el: measurement,
        warnings: measurement.data("warnings"),
        conversions: measurement.data("conversions"),
        finalized: measurement.data("finalized"),
        ageInMonths: measurement.data("age-in-months"),
        sexAtBirth: measurement.data("sex-at-birth"),
        ageInYears: measurement.data("age-in-years"),
        weightForAgeCharts: measurement.data("weight-for-age-charts"),
        weightForLengthCharts: measurement.data("weight-for-length-charts"),
        heightForAgeCharts: measurement.data("height-for-age-charts"),
        headCircumferenceForAgeCharts: measurement.data("head-circumference-for-age-charts"),
        bmiForAgeCharts: measurement.data("bmi-for-age-charts"),
        bpSystolicHeightPercentileChart: measurement.data("bp-systolic-height-percentile-charts"),
        bpDiastolicHeightPercentileChart: measurement.data("bp-diastolic-height-percentile-charts"),
        heartRateAgeCharts: measurement.data("heart-rate-age-charts"),
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
