const _ = require("underscore");
let viewExtension = Backbone.View.extend({
    events: {
        "change .replicate input[type='text']": "updateMean",
        "keyup .replicate input[type='text']": "updateMean",
        "change input, select": "inputChange",
        "keyup input": "inputKeyup",
        "change #form_blood-pressure-arm-circumference": "calculateCuff",
        "keyup #form_blood-pressure-arm-circumference": "calculateCuff",
        "change .field-irregular-heart-rate input": "calculateIrregularHeartRate",
        "change #form_pregnant, #form_wheelchair": "handleWheelchair",
        "change .field-weight input": "toggleThirdWeight",
        "change .field-height input": "toggleThirdHeight",
        "change .field-head-circumference input": "toggleThirdHeadCircumference",
        "change .field-hip-circumference input": "toggleThirdHipCircumference",
        "change .field-waist-circumference input": "toggleThirdWaistCircumference",
        "change .field-heart-rate input": "toggleThirdHeartRate",
        "change .field-blood-pressure-diastolic input,  .field-blood-pressure-systolic input": "checkDiastolic",
        "click .modification-toggle a": "showModification",
        "change .modification-select select": "handleProtocolModification",
        "click .autofill-protocol-modification": "autofillProtocolModification",
        "click .alt-units-toggle a": "enableAltUnits",
        "click .alt-units-field a": "cancelAltUnits",
        "keyup .alt-units-field input": "convertAltUnits",
        "change .alt-units-field input": "convertAltUnits",
        "change .modification-all": "handleProtocolModificationAllCheck",
        "click .modification-all": "handleProtocolModificationAllCheck"
    },
    inputChange: function (e) {
        this.clearServerErrors(e);
        this.displayWarning(e);
        this.updateConversion(e);

        let field = $(e.currentTarget).closest(".field").data("field");
        this.displayConsecutiveWarning(field, e);

        this.triggerEqualize();
    },
    inputKeyup: function (e) {
        this.updateConversion(e);
    },
    updateMean: function (e) {
        let field = $(e.currentTarget).closest(".field").data("field");
        this.calculateMean(field);
        if (field === "weight" || field === "height") {
            this.calculateBmi();
        }
    },
    triggerEqualize: function () {
        window.setTimeout(function () {
            $(window).trigger("pmi.equalize");
        }, 50);
    },
    calculateMean: function (field) {
        let fieldSelector = ".field-" + field;
        let values = [];
        let mean;
        this.$(fieldSelector)
            .find("input")
            .each(function () {
                if (parseFloat($(this).val())) {
                    values.push(parseFloat($(this).val()));
                }
            });
        const meanElement = this.$("#mean-" + field);
        if (values.length > 0) {
            if (values.length === 3) {
                values.sort(function (a, b) {
                    return a - b;
                });
                if (values[1] - values[0] < values[2] - values[1]) {
                    values.pop();
                } else if (values[2] - values[1] < values[1] - values[0]) {
                    values.shift();
                }
            }
            let sum = _.reduce(
                values,
                function (a, b) {
                    return a + b;
                },
                0
            );
            mean = (sum / values.length).toFixed(1);
            if (field === "heart-rate") {
                mean = Math.round(mean);
            }
            meanElement.html("<strong>" + mean + "</strong>");
            meanElement.attr("data-mean", mean);
            if (this.conversions[field]) {
                let converted = this.convert(this.conversions[field], mean);
                this.$("#convert-" + field).html("(" + converted + ")");
            }
            let label = values.length === 3 ? "(average of three measures)" : "(average of two closest measures)";
            this.$("#convert-" + field)
                .next()
                .html(label);
        } else {
            meanElement.text("--");
            meanElement.attr("data-mean", "");
            this.$("#convert-" + field).html("");
        }
        if (this.percentileFields.hasOwnProperty(field)) {
            let percentileFields = this.percentileFields[field];
            percentileFields.forEach((percentileField) => {
                if (percentileField === "weight-for-length") {
                    this.calculateWeightForLengthPercentileMaleFemale();
                } else {
                    mean = mean ? parseFloat(mean) : "";
                    // Calculates Growth Percentile for Weight and Height/Length
                    this.calculatePercentileMaleFemale(percentileField, mean);
                }
            });
        }
    },
    calculatePercentileMaleFemale: function (field, X) {
        const sex = this.sexAtBirth;
        if (sex === 0) {
            this.calculatePercentile(field, X, 1);
            this.calculatePercentile(field, X, 2);
        } else {
            this.calculatePercentile(field, X, sex);
        }
    },
    calculateWeightForLengthPercentileMaleFemale: function () {
        const sex = this.sexAtBirth;
        if (sex === 0) {
            this.calculateWeightForLengthPercentile(1);
            this.calculateWeightForLengthPercentile(2);
        } else {
            this.calculateWeightForLengthPercentile(sex);
        }
    },
    calculatePercentile: function (field, X, sex) {
        const lmsValues = this.getLMSValues(sex, field);
        const percentileElement = this.$("#percentile-" + sex + "-" + field);
        const zScore = X ? this.getZScore(X, lmsValues) : "";
        console.log(field, "Zscore", zScore);
        percentileElement.attr("data-zscore", zScore);
        const percentile = typeof zScore === "number" ? this.getPercentile(zScore) : "";
        console.log("percentile", percentile);
        percentileElement.html("<strong>" + this.addPercentileSuffix(percentile) + "</strong>");
        percentileElement.attr("data-percentile", percentile);
        this.handleOutOfRangePercentileWarning();
    },
    calculateWeightForLengthPercentile: function (sex) {
        const avgWeight = parseFloat($("#mean-weight").attr("data-mean"));
        const avgLength = parseFloat($("#mean-height").attr("data-mean"));
        const lmsValues = this.getWeightForLengthLMSValues(sex);
        const percentileElement = this.$("#percentile-" + sex + "-weight-for-length");
        console.log("weight-for-length", "lms", lmsValues);
        const zScore = avgWeight && avgLength ? this.getZScore(avgWeight, lmsValues) : "";
        console.log("weight-for-length", "Zscore", zScore);
        percentileElement.attr("data-zscore", zScore);
        const percentile = typeof zScore === "number" ? this.getPercentile(zScore) : "";
        percentileElement.html("<strong>" + this.addPercentileSuffix(percentile) + "</strong>");
        percentileElement.attr("data-percentile", percentile);
        this.handleOutOfRangePercentileWarning();
    },
    getLMSValues: function (sex, field) {
        const ageInMonths = this.ageInMonths;
        let lmsValues = [];
        let charts = this.growthCharts[field];
        charts.forEach((item) => {
            if (item.sex === sex && Math.floor(item.month) === ageInMonths) {
                lmsValues["L"] = item.L;
                lmsValues["M"] = item.M;
                lmsValues["S"] = item.S;
            }
        });
        console.log(field, "lms", lmsValues);
        return lmsValues;
    },
    getWeightForLengthLMSValues: function (sex) {
        const avgWeight = parseFloat($("#mean-weight").attr("data-mean"));
        const avgLength = parseFloat($("#mean-height").attr("data-mean"));
        let lmsValues = [];
        if (avgWeight && avgLength) {
            let charts = this.growthCharts["weight-for-length"];
            charts.forEach((item) => {
                if (item.sex === sex && Math.round(item.length) === Math.round(avgLength)) {
                    lmsValues["L"] = item.L;
                    lmsValues["M"] = item.M;
                    lmsValues["S"] = item.S;
                }
            });
        }
        console.log("weight-for-length", "lms", lmsValues);
        return lmsValues;
    },
    getZScore: function (X, lmsValues) {
        const L = parseFloat(lmsValues["L"]);
        const M = parseFloat(lmsValues["M"]);
        const S = parseFloat(lmsValues["S"]);
        if (L !== 0) {
            const numerator = Math.pow(X / M, L) - 1;
            const denominator = L * S;
            if (denominator !== 0) {
                return parseFloat((numerator / denominator).toFixed(2));
            }
        } else {
            if (S !== 0) {
                return parseFloat((Math.log(X / M) / S).toFixed(2));
            }
        }
    },
    getPercentile: function (z) {
        const zScores = this.zScoreCharts;
        const decimalPoints = {
            Z_0: 0.0,
            Z_01: 0.01,
            Z_02: 0.02,
            Z_03: 0.03,
            Z_04: 0.04,
            Z_05: 0.05,
            Z_06: 0.06,
            Z_07: 0.07,
            Z_08: 0.08,
            Z_09: 0.09
        };
        for (const zScore of zScores) {
            if (z === zScore["Z"]) {
                return Math.round(zScore["Z_0"] * 100);
            }
            for (const [index, decimalPoint] of Object.entries(decimalPoints)) {
                // Handle -0 & +0 ZScore rows
                const newZValue =
                    Object.is(0, zScore["Z"]) || zScore["Z"] > 0
                        ? zScore["Z"] + decimalPoint
                        : zScore["Z"] - decimalPoint;
                if (z === parseFloat(newZValue.toFixed(2))) {
                    let percentile = zScore[index] * 100;
                    if (percentile < 3) {
                        percentile = percentile.toFixed(1);
                        if (percentile % 1 !== 0) {
                            return percentile;
                        }
                    }
                    return Math.round(percentile);
                }
            }
        }
        return "";
    },
    getX: function (zScore, lmsValues) {
        const L = parseFloat(lmsValues["L"]);
        const M = parseFloat(lmsValues["M"]);
        const S = parseFloat(lmsValues["S"]);
        if (L === 0) {
            return null;
        }
        return M * Math.pow(zScore * L * S + 1, 1 / L);
    },
    handleOutOfRangePercentileWarning: function () {
        const displayWarning = (percentileIds, warningFieldId) => {
            let hasWarning = false;
            for (const percentileId of percentileIds) {
                const percentileField = $("#" + percentileId);
                if (
                    percentileField &&
                    percentileField.attr("data-zscore") &&
                    percentileField.attr("data-percentile") === ""
                ) {
                    hasWarning = true;
                    break;
                }
            }
            $("#" + warningFieldId).toggle(hasWarning);
        };
        const weightLengthPercentileIds = [
            "percentile-1-weight-for-age",
            "percentile-2-weight-for-age",
            "percentile-1-height-for-age",
            "percentile-2-height-for-age",
            "percentile-1-weight-for-length",
            "percentile-2-weight-for-length"
        ];
        displayWarning(weightLengthPercentileIds, "weight-length-percentile-warning");

        const headCircumferencePercentileIds = [
            "percentile-1-head-circumference-for-age",
            "percentile-2-head-circumference-for-age"
        ];
        displayWarning(headCircumferencePercentileIds, "head-circumference-percentile-warning");

        const bmiPercentileIds = ["percentile-1-bmi-for-age", "percentile-2-bmi-for-age"];
        displayWarning(bmiPercentileIds, "bmi-percentile-warning");
    },
    calculateBmi: function () {
        let height = parseFloat(this.$("#mean-height").attr("data-mean"));
        let weight = parseFloat(this.$("#mean-weight").attr("data-mean"));
        this.$("#bmi-warning").text("");
        if (height && weight) {
            let bmi = weight / ((height / 100) * (height / 100));
            bmi = bmi.toFixed(1);
            const bmiElement = this.$("#bmi");
            bmiElement.html("<strong>" + bmi + "</strong>");
            bmiElement.attr("data-bmi", bmi);
            this.calculatePercentileMaleFemale("bmi-for-age", parseFloat(bmi));
            if (bmi < 10 || bmi > 31) {
                this.$("#bmi-warning").text(
                    "Please verify that the weight and height measurement are correct. The calculated value might be outside the expected range for this age group based on the provided weight and height."
                );
            }
        } else {
            this.$("#bmi").text("--");
            this.$("#percentile-1-bmi-for-age").text("--");
            this.$("#percentile-2-bmi-for-age").text("--");
        }
    },
    calculateCuff: function () {
        let circumference = parseFloat(this.$("#form_blood-pressure-arm-circumference").val());
        if (!circumference || circumference < 22 || circumference > 52) {
            this.$("#cuff-size").text("--");
        } else if (circumference < 27) {
            this.$("#cuff-size").text("Small adult (12×22 cm)");
        } else if (circumference < 35) {
            this.$("#cuff-size").text("Adult (16×30 cm)");
        } else if (circumference < 45) {
            this.$("#cuff-size").text("Large adult (16×36 cm)");
        } else {
            this.$("#cuff-size").text("Adult thigh (16×42 cm)");
        }
    },
    handleWheelchair: function () {
        let isWheelchairUser = this.$("#form_wheelchair").val() == 1;
        let self = this;
        if (isWheelchairUser) {
            this.$("#panel-waist input").each(function () {
                $(this).valChange("");
            });
            this.$("#panel-waist input, #panel-waist select").each(function () {
                $(this).attr("disabled", true);
            });
            this.$("#waist-skip").html('<span class="label label-danger">Skip</span>');
            this.$("#panel-waist, #panel-waist-mean").hide();
        }
        if (isWheelchairUser) {
            if (this.rendered) {
                this.$(".field-weight-protocol-modification select, .field-height-protocol-modification select").each(
                    function () {
                        $(this).valChange("wheelchair-user");
                    }
                );
            }
        }
        if (!isWheelchairUser) {
            if (this.rendered) {
                this.$(".field-weight-protocol-modification select, .field-height-protocol-modification select").each(
                    function () {
                        if ($(this).val() === "wheelchair-user") {
                            $(this).valChange("");
                        }
                    }
                );
            }
        }
        if (!isWheelchairUser) {
            this.$("#panel-waist input, #panel-waist select").each(function () {
                if (!self.finalized) {
                    $(this).attr("disabled", false);
                }
                if ($(this).closest(".modification-block").length > 0) {
                    self.handleProtocolModificationBlock($(this).closest(".modification-block"));
                }
            });
            this.$("#waist-skip").text("");
            this.$("#panel-waist, #panel-waist-mean").show();
        }
    },
    toggleThirdReading: function (field) {
        let first = parseFloat(this.$("#form_" + field + "_0").val());
        let second = parseFloat(this.$("#form_" + field + "_1").val());
        let difference = 1;
        switch (field) {
            case "weight":
                difference = 0.1;
                break;
            case "heart-rate":
                difference = 5;
                break;
        }
        if (first > 0 && second > 0 && Math.abs(first - second).toFixed(2) > difference) {
            this.$(".panel-" + field + "-3").show();
        } else {
            this.$(".panel-" + field + "-3").hide();
            this.$(".panel-" + field + "-3 input, .panel-" + field + "-3 select").each(function () {
                $(this).valChange("");
            });
        }
    },
    toggleThirdWeight: function () {
        this.toggleThirdReading("weight");
    },
    toggleThirdHeight: function () {
        this.toggleThirdReading("height");
    },
    toggleThirdHeadCircumference: function () {
        this.toggleThirdReading("head-circumference");
    },
    toggleThirdHipCircumference: function () {
        this.toggleThirdReading("hip-circumference");
    },
    toggleThirdWaistCircumference: function () {
        this.toggleThirdReading("waist-circumference");
    },
    toggleThirdHeartRate: function () {
        const fieldsToCheck = ["heart-rate", "blood-pressure-systolic", "blood-pressure-diastolic"];
        let showMeasurementPanel = false;
        for (const field of fieldsToCheck) {
            let first = parseFloat(this.$("#form_" + field + "_0").val());
            let second = parseFloat(this.$("#form_" + field + "_1").val());
            if (first > 0 && second > 0 && Math.abs(first - second) > 5) {
                showMeasurementPanel = true;
                break;
            }
        }
        if (showMeasurementPanel) {
            $(".panel-heart-rate-3").show();
        } else {
            $(".panel-heart-rate-3").hide();
            $(".panel-heart-rate-3 input, .panel-heart-rate-3 select").each(function () {
                $(this).valChange("");
            });
        }
    },
    calculateIrregularHeartRate: function () {
        const isIrregular = this.$(".field-irregular-heart-rate input").is(":checked");
        const irregularHeartRateWarning = $("#irregular-heart-rate-warning");
        if (isIrregular && !irregularHeartRateWarning.find(".alert-danger").length > 0) {
            irregularHeartRateWarning.html(
                '<div class="alert alert-danger"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> Refer to your site\'s SOP for irregular heart rhythm detection.</div>'
            );
            if (this.rendered) {
                new PmiAlertModal({
                    msg: "Refer to your site's SOP for irregular heart rhythm detection.",
                    onFalse: function () {
                        input.val("");
                        input.focus();
                        input.trigger("change");
                    },
                    btnTextTrue: "Confirm and take action"
                });
            }
        }
        if (!isIrregular) {
            irregularHeartRateWarning.text("");
        }
    },
    checkDiastolic: function (e) {
        let replicate = $(e.currentTarget).closest(".form-group").data("replicate");
        let systolic = parseFloat(
            this.$(".field-blood-pressure-systolic[data-replicate=" + replicate + "] input").val()
        );
        let diastolic = parseFloat(
            this.$(".field-blood-pressure-diastolic[data-replicate=" + replicate + "] input").val()
        );
        let container = this.$(".field-blood-pressure-diastolic[data-replicate=" + replicate + "]").closest(
            ".form-group"
        );
        container.find(".diastolic-warning").remove();
        if (systolic && diastolic && diastolic >= systolic) {
            container.append(
                $('<div class="diastolic-warning text-warning">').text(
                    "Diastolic pressure must be less than systolic pressure"
                )
            );
        }
        this.toggleThirdHeartRate();
    },
    clearServerErrors: function (e) {
        let field = $(e.currentTarget).closest(".field");
        field.find("span.help-block ul li").remove();
    },
    kgToLb: function (kg) {
        return (parseFloat(kg) * 2.2046).toFixed(1);
    },
    cmToIn: function (cm) {
        return (parseFloat(cm) * 0.3937).toFixed(1);
    },
    lbToKg: function (lb) {
        return (parseFloat(lb) / 2.2046).toFixed(1);
    },
    inToCm: function (inches) {
        return (parseFloat(inches) / 0.3937).toFixed(1);
    },
    convert: function (type, val) {
        switch (type) {
            case "in":
                return this.cmToIn(val) + " in";
            case "ftin":
                let inches = this.cmToIn(val);
                let feet = Math.floor(inches / 12);
                inches = (inches % 12).toFixed();
                return feet + "ft " + inches + "in";
            case "lb":
                return this.kgToLb(val) + " lb";
            default:
                return false;
        }
    },
    updateConversion: function (e) {
        let field = $(e.currentTarget).closest(".field").data("field");
        let replicate = $(e.currentTarget).closest(".field").data("replicate");
        let index = null;
        if (replicate) {
            index = parseInt(replicate) - 1;
        }
        this.calculateConversion(field, index);
    },
    calculateConversion: function (field, index = null) {
        let input = this.$(".field-" + field).find("input");
        let convertFieldId = "#convert-" + field;
        if (index !== null) {
            input = this.$("#form_" + field + "_" + index);
            convertFieldId = "#convert-" + field + "_" + index;
        }
        let val = null;
        if (this.recordUserValues[field]) {
            if (field === "height") {
                let feet = parseFloat($(`#form_height-ft-user-entered_${index}`).val());
                let inches = parseFloat($(`#form_height-in-user-entered_${index}`).val());
                if (!Number.isNaN(feet) && !Number.isNaN(inches)) {
                    val = `${feet}ft ${inches}in`;
                }
            } else {
                let inputVal = parseFloat($(input).closest(".panel-body").find(`input.alt-units-${field}`).val());
                if (!Number.isNaN(inputVal)) {
                    val = `${inputVal} ${this.conversions[field]}`;
                }
            }
        }
        if (this.conversions[field] && (val === null || Number.isNaN(val))) {
            val = parseFloat(input.val());
            if (val) {
                var converted = this.convert(this.conversions[field], val);
                if (converted) {
                    val = converted;
                } else {
                    val = null;
                }
            }
        }
        if (val) {
            this.$(convertFieldId).text("(" + val + ")");
        } else {
            this.$(convertFieldId).text("");
        }
    },
    warningConditionMet: function (warning, val) {
        if (warning.val && val == warning.val) {
            return true;
        }
        val = parseFloat(val);
        if (!val) {
            return false;
        }
        if (warning.hasOwnProperty("customPercentile")) {
            if (warning.customPercentile === "heart-rate") {
                let maxMinValue = null;
                for (const heartRate of this.heartRateAgeCharts) {
                    if (this.ageInMonths >= heartRate.startAge && this.ageInMonths < heartRate.endAge) {
                        maxMinValue = heartRate[warning.percentileField];
                        break;
                    }
                }
                warning[warning.percentileType] = maxMinValue;
                console.log(warning.customPercentile, "warningValue", maxMinValue);
                return this.warningCondition(warning, val);
            }
            if (warning.customPercentile === "bp-systolic" || warning.customPercentile === "bp-diastolic") {
                let heightPercentileField = "heightPer5";
                let heightPercentile = $("#percentile-" + this.sexAtBirth + "-height-for-age").attr("data-percentile");
                if (heightPercentile) {
                    const nearestPercentile = this.roundDownToNearestPercentile(heightPercentile);
                    heightPercentileField = "heightPer" + nearestPercentile;
                }
                const bpHeightPercentileCharts =
                    warning.customPercentile === "bp-systolic"
                        ? this.bpSystolicHeightPercentileChart
                        : this.bpDiastolicHeightPercentileChart;

                if (this.sexAtBirth === 0) {
                    let heightPercentileFieldMale,
                        heightPercentileFieldFemale = "heightPer5";
                    let heightPercentileMale = $("#percentile-1-height-for-age").attr("data-percentile");
                    let heightPercentileFemale = $("#percentile-2-height-for-age").attr("data-percentile");
                    if (heightPercentileMale) {
                        const nearestPercentileMale = this.roundDownToNearestPercentile(heightPercentileMale);
                        heightPercentileFieldMale = "heightPer" + nearestPercentileMale;
                    }
                    if (heightPercentileFemale) {
                        const nearestPercentileFemale = this.roundDownToNearestPercentile(heightPercentileFemale);
                        heightPercentileFieldFemale = "heightPer" + nearestPercentileFemale;
                    }
                    const maxValueMale = this.getMaxValueForPercentile(
                        warning,
                        1,
                        heightPercentileFieldMale,
                        bpHeightPercentileCharts,
                        this.ageInYears
                    );
                    const maxValueFemale = this.getMaxValueForPercentile(
                        warning,
                        2,
                        heightPercentileFieldFemale,
                        bpHeightPercentileCharts,
                        this.ageInYears
                    );
                    console.log(warning.customPercentile, "warningValue-male-female", maxValueMale, maxValueFemale);
                    return val >= maxValueMale || val >= maxValueFemale;
                }
                const maxValue = this.getMaxValueForPercentile(
                    warning,
                    this.sexAtBirth,
                    heightPercentileField,
                    bpHeightPercentileCharts,
                    this.ageInYears
                );
                console.log(warning.customPercentile, "warningValue", maxValue);
                return val >= maxValue;
            }
        }
        if (warning.hasOwnProperty("deviation")) {
            let deviationField = warning.deviation;
            let deviationMale, deviationFemale, conditionMale, conditionFemale;
            if (this.sexAtBirth === 0) {
                deviationMale = $("#percentile-1-" + deviationField).attr("data-zscore");
                deviationFemale = $("#percentile-2-" + deviationField).attr("data-zscore");
                conditionMale = deviationMale !== "" && parseFloat(deviationMale) > warning.max;
                conditionFemale = deviationFemale !== "" && parseFloat(deviationFemale) > warning.max;
                return conditionMale || conditionFemale;
            }
            let zscore = $("#percentile-" + this.sexAtBirth + "-" + deviationField).attr("data-zscore");
            return zscore !== "" && parseFloat(zscore) > warning.max;
        }
        if (warning.hasOwnProperty("percentile")) {
            let percentileField = warning.percentile;
            let percentileMale, percentileFemale, conditionMale, conditionFemale;
            const twoThirdPercentileZScore = -2;
            const thirdPercentileZScore = -1.88;
            const avgWeight = parseFloat($("#mean-weight").attr("data-mean"));
            if (this.sexAtBirth === 0) {
                percentileMale = $("#percentile-1-" + percentileField).attr("data-percentile");
                percentileFemale = $("#percentile-2-" + percentileField).attr("data-percentile");
                // Fallback calculation if weight-for-age/weight-for-length percentile can't be calculated
                if (percentileMale === "" || percentileFemale === "") {
                    if (warning.percentile === "weight-for-age") {
                        conditionMale =
                            percentileMale === "" &&
                            val < this.getX(thirdPercentileZScore, this.getLMSValues(1, percentileField));
                        conditionFemale =
                            percentileFemale === "" &&
                            val < this.getX(thirdPercentileZScore, this.getLMSValues(2, percentileField));
                    }
                    if (warning.percentile === "weight-for-length") {
                        conditionMale =
                            percentileMale === "" &&
                            avgWeight < this.getX(twoThirdPercentileZScore, this.getWeightForLengthLMSValues(1));
                        conditionFemale =
                            percentileFemale === "" &&
                            avgWeight < this.getX(twoThirdPercentileZScore, this.getWeightForLengthLMSValues(2));
                    }
                    if (conditionMale || conditionFemale) {
                        return true;
                    }
                }
                conditionMale = percentileMale !== "" && parseFloat(percentileMale) < warning.min;
                conditionFemale = percentileFemale !== "" && parseFloat(percentileFemale) < warning.min;
                return conditionMale || conditionFemale;
            }
            let percentile = $("#percentile-" + this.sexAtBirth + "-" + percentileField).attr("data-percentile");
            if (percentile === "") {
                if (warning.percentile === "weight-for-age") {
                    return val < this.getX(thirdPercentileZScore, this.getLMSValues(this.sexAtBirth, percentileField));
                }
                if (warning.percentile === "weight-for-length") {
                    return (
                        avgWeight <
                        this.getX(twoThirdPercentileZScore, this.getWeightForLengthLMSValues(this.sexAtBirth))
                    );
                }
            }
            return percentile !== "" && parseFloat(percentile) < warning.min;
        }
        if (warning.hasOwnProperty("age")) {
            if (this.ageInMonths > warning.age[0] && this.ageInMonths < warning.age[1]) {
                return this.warningCondition(warning, val);
            }
            return false;
        }
        return this.warningCondition(warning, val);
    },
    getMaxValueForPercentile: function (warning, sex, heightPercentileField, bpHeightPercentileCharts, ageInYears) {
        let maxValue = null;
        for (const bpHeightPercentile of bpHeightPercentileCharts) {
            if (
                bpHeightPercentile.sex === sex &&
                ageInYears === bpHeightPercentile.ageYear &&
                bpHeightPercentile.bpCentile === 95
            ) {
                maxValue = bpHeightPercentile[heightPercentileField] + warning.addValue;
                break;
            }
        }
        if (warning.hasOwnProperty("maxValue") && maxValue > warning.maxValue) {
            maxValue = warning.maxValue;
        }
        return maxValue;
    },
    warningCondition: function (warning, val) {
        return (
            (warning.min && val < warning.min) ||
            (warning.max && val > warning.max) ||
            (warning.between && val > warning.between[0] && val < warning.between[1])
        );
    },
    displayWarnings: function () {
        let self = this;
        _.each(this.warnings, function (warnings, field) {
            this.$(".field-" + field)
                .find("input, select")
                .each(function () {
                    let input = $(this);
                    let field = input.closest(".field").data("field");
                    let container = input.closest(".form-group");
                    container.find(".metric-warnings").remove();
                    if (container.find(".metric-errors div").length > 0) {
                        return;
                    }
                    let val = input.val();
                    $.each(warnings, function (key, warning) {
                        if (!warning.consecutive && self.warningConditionMet(warning, val)) {
                            if (
                                (warning.hasOwnProperty("percentile") && warning.percentile === "weight-for-length") ||
                                warning.percentile === "bmi-for-age"
                            ) {
                                $("#" + warning.percentile + "-warning").html(warning.message);
                                return true;
                            } else {
                                container.append($('<div class="metric-warnings text-warning">').text(warning.message));
                            }
                            if (warning.hasOwnProperty("percentile") && warning.percentile === "weight-for-age") {
                                return true;
                            }
                            return false; // only show first (highest priority) warning
                        }
                    });
                });
        });
    },
    displayConsecutiveWarning: function (field, e) {
        let self = this;
        if (this.$(".field-" + field).closest(".replicate").length === 0) {
            // ignore non-replicate fields
            return;
        }
        if (!this.warnings[field]) {
            // ignore if no warnings on this field
            return;
        }
        // clear out previous warning
        this.$("#" + field + "-warning").text("");

        // get all replicate field values
        let values = [];
        this.$(".field-" + field + " input").each(function () {
            values.push($(this).val());
        });
        let warned = false;
        $.each(this.warnings[field], function (key, warning) {
            if (!warning.consecutive) {
                return false;
            }
            let consecutiveConditionsMet = 0;
            let isConsecutive = false;
            $.each(values, function (k, val) {
                if (self.warningConditionMet(warning, val)) {
                    consecutiveConditionsMet++;
                    if (consecutiveConditionsMet >= 2) {
                        isConsecutive = true;
                    }
                } else {
                    consecutiveConditionsMet = 0;
                }
            });
            if (isConsecutive) {
                if (e && self.rendered) {
                    let input = $(e.currentTarget);
                    new PmiConfirmModal({
                        msg: warning.message,
                        isHTML: true,
                        onFalse: function () {
                            input.val("");
                            input.focus();
                            input.trigger("change");
                        },
                        btnTextTrue: "Confirm value and take action",
                        btnTextFalse: "Clear value and reenter"
                    });
                }
                if (
                    (warning.hasOwnProperty("percentile") && warning.percentile === "weight-for-length") ||
                    warning.percentile === "bmi-for-age"
                ) {
                    field = warning.percentile;
                }
                self.$("#" + field + "-warning").html(
                    '<div class="alert alert-danger"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> ' +
                        warning.message +
                        "</div>"
                );
                return false; // only show first (highest priority) warning
            }
        });
    },
    displayWarning: function (e) {
        let self = this;
        let input;
        if (e.hasOwnProperty("currentTarget")) {
            input = $(e.currentTarget);
        } else {
            input = e;
        }
        let field = input.closest(".field").data("field");
        let container = input.closest(".form-group");
        container.find(".metric-warnings").remove();
        if (["height", "weight"].includes(field)) {
            $("#weight-for-length-warning, #bmi-for-age-warning").text("");
        }
        if (container.find(".metric-errors div").length > 0) {
            return;
        }
        let val = input.val();
        if (val === "") {
            this.displayWarnings();
        }
        if (this.warnings[field]) {
            let warned = false;
            $.each(this.warnings[field], function (key, warning) {
                if (!warning.consecutive && self.warningConditionMet(warning, val)) {
                    if (warning.alert) {
                        new PmiConfirmModal({
                            isHTML: true,
                            msg: warning.message,
                            onFalse: function () {
                                input.val("");
                                input.focus();
                                input.trigger("change");
                            },
                            btnTextTrue: "Confirm value and take action",
                            btnTextFalse: "Clear value and reenter"
                        });
                    }
                    if (
                        (warning.hasOwnProperty("percentile") && warning.percentile === "weight-for-length") ||
                        warning.percentile === "bmi-for-age"
                    ) {
                        $("#" + warning.percentile + "-warning").html(warning.message);
                        return true;
                    } else {
                        container.append($('<div class="metric-warnings text-warning">').text(warning.message));
                    }
                    if (warning.hasOwnProperty("percentile") && warning.percentile === "weight-for-age") {
                        return true;
                    }
                    return false; // only show first (highest priority) warning
                }
            });
        }
    },
    roundDownToNearestPercentile: function (percentile) {
        const percentiles = [5, 10, 25, 50, 75, 90, 95];
        let result = percentiles[0];

        for (const value of percentiles) {
            if (percentile >= value) {
                result = value;
            } else {
                break;
            }
        }
        return result;
    },
    handleProtocolModificationAllCheck: function (e) {
        if (!$(e.currentTarget).is(":checked")) {
            return;
        }
        let block = $(e.currentTarget).closest(".modification-block");
        let primarySelect = block.find(".modification-select select");
        let modificationType = $(e.currentTarget).parents(".modification-select").data("modification-type");
        let elements = [];
        if ($(e.currentTarget).is(":checked")) {
            elements = $(modificationType + "-select")
                .find("select")
                .not(primarySelect)
                .val(primarySelect.val())
                .closest(".modification-block");
        }
        for (let i = 0; i < elements.length; i++) {
            this.showModificationBlock($(elements[i]));
            this.handleProtocolModificationBlock($(elements[i]));
            this.triggerEqualize();
            this.displayWarning($(elements[i]));
        }
    },
    handleProtocolModification: function (e) {
        let block = $(e.currentTarget).closest(".modification-block");
        let modificationType = $(e.currentTarget).parents(".modification-select").data("modification-type");
        let modificationId = block.find(".modification-select select").attr("id");
        let isFirstModification = modificationId.endsWith("_0");
        if ($(modificationType + "-all").is(":checked") && isFirstModification) {
            let elements = $(modificationType + "-select")
                .find("select")
                .not(e.currentTarget)
                .val(e.currentTarget.value)
                .closest(".modification-block");
            for (let i = 0; i < elements.length; i++) {
                this.showModificationBlock($(elements[i]));
                this.handleProtocolModificationBlock($(elements[i]));
                this.triggerEqualize();
                this.displayWarning($(elements[i]));
            }
        }
        this.handleProtocolModificationBlock(block);
    },
    handleProtocolModificationBlock: function (block) {
        let modification = block.find(".modification-select select").val();
        let manualMeasurement = block.find(".modification-manual input:checkbox").is(":checked");
        let self = this;
        if (modification === "" && manualMeasurement === false) {
            block.find(".modification-select").hide();
            block.find(".modification-toggle").show();
        } else {
            block.find(".modification-toggle").hide();
            block.find(".modification-select").show();
        }
        if (
            modification === "parental-refusal" ||
            modification === "child-dissenting-behavior" ||
            modification === "colostomy-bag" ||
            modification === "cannot-balance-on-scale" ||
            modification === "crying"
        ) {
            block.find(".modification-affected input:text, .modification-affected select").each(function () {
                $(this).valChange("").attr("disabled", true);
            });
            block.find(".modification-manual input:checkbox").each(function () {
                $(this).prop("checked", false).attr("disabled", true);
            });
            block.find(".alt-units-block").hide();
        } else {
            block
                .find(
                    ".modification-affected input, .modification-affected select, .modification-manual input:checkbox"
                )
                .each(function () {
                    if (!self.finalized) {
                        $(this).attr("disabled", false);
                    }
                });
            block.find(".alt-units-block").show();
        }
        if (modification === "other") {
            block.find(".modification-notes").show();
        } else {
            block.find(".modification-notes").hide();
            block.find(".modification-notes input").val("");
        }
        this.triggerEqualize();
    },
    showModificationBlock: function (block) {
        block.find(".modification-toggle").hide();
        block.find(".modification-select").show();
    },
    showModification: function (e) {
        let block = $(e.currentTarget).closest(".modification-block");
        this.showModificationBlock(block);
        this.triggerEqualize();
    },
    showModifications: function () {
        let self = this;
        this.$(".modification-block").each(function () {
            self.handleProtocolModificationBlock($(this));
        });
    },
    autofillProtocolModification: function (e) {
        let self = this;
        let reason = $(e.currentTarget).data("reason");
        this.$(".modification-block").each(function () {
            let modification = $(this).find(".modification-select select").val();
            if (!modification) {
                let needsModification = false;
                $(this)
                    .find(".modification-affected input[type=text]:visible")
                    .each(function () {
                        if (!$(this).val()) {
                            needsModification = true;
                        }
                    });
                if (needsModification) {
                    $(this).find(".modification-select select").val(reason);
                    self.handleProtocolModificationBlock($(this));
                }
            }
        });
        _.each(["height", "weight"], function (field) {
            if (!$("#form_" + field).val() && !$("#form_" + field + "-protocol-modification").val()) {
                $("#form_" + field + "-protocol-modification").val(reason);
            }
        });
    },
    enableAltUnits: function (e) {
        let block = $(e.currentTarget).closest(".alt-units-block");
        block.find(".alt-units-field").show();
        block.find(".alt-units-toggle").hide();
        block.prev().find("input").attr("readonly", true);
        this.triggerEqualize();
    },
    cancelAltUnits: function (e) {
        let block = $(e.currentTarget).closest(".alt-units-block");
        block.find(".alt-units-toggle").show();
        block.find(".alt-units-field").hide();
        block.prev().find("input").attr("readonly", false);
        block.find(".alt-units-field input").val("");
        this.triggerEqualize();
    },
    convertAltUnits: function (e) {
        let block = $(e.currentTarget).closest(".alt-units-field");
        let type = block.find("label").attr("for");
        let val;
        if (type == "alt-units-height-ftin") {
            let inches = 0;
            let ft = parseFloat(block.find("[id^=form_height-ft-user-entered]").val());
            if (ft) {
                inches += 12 * ft;
            }
            let inch = parseFloat(block.find("[id^=form_height-in-user-entered]").val());
            if (inch) {
                inches += inch;
            }
            val = this.inToCm(inches);
        } else if (type == "alt-units-height-ftin") {
            let inches = 0;
            let ft = parseFloat($("#form_height-ft-user-entered").val());
            if (ft) {
                inches += 12 * ft;
            }
            let inch = parseFloat($("#form_height-in-user-entered").val());
            if (inch) {
                inches += inch;
            }
            val = this.inToCm(inches);
        } else {
            let unit = block.find(".input-group-addon").text();
            val = block.find("input").val();
            if (unit == "in") {
                val = this.inToCm(val);
            } else if (unit == "lb") {
                val = this.lbToKg(val);
            }
        }
        if (isNaN(val)) {
            val = "";
        }
        let input = block.parent().prev().find("input");
        input.val(val);
        if (e.type == "change") {
            block.parent().prev().find("input").trigger("change"); // trigger change even if not different
            block.parent().prev().find("input").parsley().validate(); // trigger parsley validation
        }
    },
    addPercentileSuffix: function (percentile) {
        if (percentile === "") {
            return "--";
        }
        const integerPart = Math.floor(percentile);

        if (integerPart >= 11 && integerPart <= 13) {
            return percentile + "th";
        }

        const lastDigit = parseInt(percentile.toString().split("").reverse()[0], 10);

        switch (lastDigit % 10) {
            case 1:
                return percentile + "st";
            case 2:
                return percentile + "nd";
            case 3:
                return percentile + "rd";
            default:
                return percentile + "th";
        }
    },
    // for parsley validator
    validateHeightWeight: function (height, weight) {
        if (height && weight) {
            let bmi = weight / ((height / 100) * (height / 100));
            bmi = bmi.toFixed(1);
            if (bmi < 5 || bmi > 125) {
                return false;
            }
        }
        return true;
    },
    initParsley: function () {
        self = this;
        window.Parsley.addValidator("bmiHeight", {
            validateString: function (value, weightSelector) {
                let height = parseFloat(value);
                let weight = parseRequirement(weightSelector);
                return self.validateHeightWeight(height, weight);
            },
            messages: {
                en: "This height/weight combination has yielded an invalid BMI"
            },
            priority: 32
        });
        window.Parsley.addValidator("bmiWeight", {
            validateString: function (value, heightSelector) {
                let weight = parseFloat(value);
                let height = parseRequirement(heightSelector);
                return self.validateHeightWeight(height, weight);
            },
            messages: {
                en: "This height/weight combination has yielded an invalid BMI"
            },
            priority: 32
        });

        this.$("form").parsley({
            errorClass: "has-error",
            classHandler: function (el) {
                return el.$element.closest(".form-group");
            },
            errorsContainer: function (el) {
                return el.$element.closest(".form-group");
            },
            errorsWrapper: '<div class="metric-errors help-block"></div>',
            errorTemplate: "<div></div>",
            trigger: "keyup change"
        });
    },
    initialize: function (obj) {
        this.ageInMonths = parseInt(obj.ageInMonths);
        if (obj.warnings.hasOwnProperty("weight")) {
            obj.warnings.weight = obj.warnings.weight.filter((warning) => {
                return !(
                    warning.hasOwnProperty("ageRange") &&
                    (this.ageInMonths < warning.ageRange[0] || this.ageInMonths > warning.ageRange[1])
                );
            });
        }
        this.warnings = obj.warnings;
        this.conversions = obj.conversions;
        this.recordUserValues = obj.recordUserValues;
        this.finalized = obj.finalized;
        this.ageInYears = parseInt(obj.ageInYears);
        this.sexAtBirth = obj.sexAtBirth;
        console.log("ageInMonths", this.ageInMonths);
        this.bpSystolicHeightPercentileChart = obj.bpSystolicHeightPercentileChart;
        this.bpDiastolicHeightPercentileChart = obj.bpDiastolicHeightPercentileChart;
        this.heartRateAgeCharts = obj.heartRateAgeCharts;
        this.zScoreCharts = obj.zScoreCharts;
        this.rendered = false;
        this.requireConversionFields = [
            "hip-circumference",
            "waist-circumference",
            "head-circumference",
            "weight",
            "height"
        ];
        this.meanFields = [
            "weight",
            "height",
            "heart-rate",
            "hip-circumference",
            "waist-circumference",
            "head-circumference",
            "blood-pressure-systolic",
            "blood-pressure-diastolic"
        ];
        this.percentileFields = {
            weight: ["weight-for-age", "weight-for-length"],
            height: ["height-for-age", "weight-for-length"],
            "head-circumference": ["head-circumference-for-age"]
        };
        this.growthCharts = {
            "weight-for-age": obj.weightForAgeCharts,
            "weight-for-length": obj.weightForLengthCharts,
            "height-for-age": obj.heightForAgeCharts,
            "head-circumference-for-age": obj.headCircumferenceForAgeCharts,
            "bmi-for-age": obj.bmiForAgeCharts
        };
        this.render();
    },
    render: function () {
        let self = this;
        self.initParsley();

        let processedReplicates = {};
        this.$(".replicate .field").each(function () {
            let field = $(this).data("field");
            if (!processedReplicates[field]) {
                if ($.inArray(field, self.meanFields) !== -1) {
                    self.calculateMean(field);
                }
                self.displayConsecutiveWarning(field);
                processedReplicates[field] = true;
            }
        });

        _.each(_.keys(this.conversions), function (field) {
            if ($.inArray(field, self.requireConversionFields) !== -1) {
                let replicates = $(".field-" + field).length;
                for (let i = 0; i < replicates; i++) {
                    self.calculateConversion(field, i);
                }
            } else {
                self.calculateConversion(field);
            }
        });
        this.showModifications();
        this.displayWarnings();
        this.calculateBmi();
        this.calculateCuff();
        this.calculateIrregularHeartRate();
        this.handleWheelchair();
        this.toggleThirdWeight();
        this.toggleThirdHeight();
        this.toggleThirdHeadCircumference();
        this.toggleThirdHipCircumference();
        this.toggleThirdWaistCircumference();
        this.toggleThirdHeartRate();
        if (this.finalized) {
            this.$(".modification-toggle").hide();
            this.$(".alt-units-block").hide();
        }
        this.triggerEqualize();
        this.rendered = true;
        return this;
    }
});
PMI.views["PhysicalEvaluation-0.3-peds"] = viewExtension;
PMI.views["PhysicalEvaluation-0.3-peds-weight"] = viewExtension;
