const _ = require("underscore");

/**
 * Physical evaluation form view
 */

/* eslint security/detect-object-injection: "off" */

PMI.views["PhysicalEvaluation-0.2"] = Backbone.View.extend({
    events: {
        "click .toggle-help-image": "displayHelpModal",
        "change .replicate input": "updateMean",
        "keyup .replicate input": "updateMean",
        "change input, select": "inputChange",
        "keyup input": "inputKeyup",
        "keyup #form_height, #form_weight": "calculateBmi",
        "change #form_height, #form_weight": "calculateBmi",
        "change #form_blood-pressure-arm-circumference": "calculateCuff",
        "keyup #form_blood-pressure-arm-circumference": "calculateCuff",
        "change .field-irregular-heart-rate input": "calculateIrregularHeartRate",
        "change #form_pregnant, #form_wheelchair": "handlePregnantOrWheelchair",
        "change #form_height-protocol-modification": "handleHeightProtocol",
        "change #form_weight-protocol-modification": "handleWeightProtocol",
        "change #form_waist-circumference-protocol-modification": "handleWaistProtocol",
        "change .field-hip-circumference input": "toggleThirdHipCircumference",
        "change .field-waist-circumference input": "toggleThirdWaistCircumference",
        "change .field-blood-pressure-diastolic input,  .field-blood-pressure-systolic input": "checkDiastolic"
    },
    inputChange: function (e) {
        this.clearServerErrors(e);
        this.displayWarning(e);
        this.updateConversion(e);

        var field = $(e.currentTarget).closest(".field").data("field");
        this.displayConsecutiveWarning(field, e);

        this.triggerEqualize();
    },
    inputKeyup: function (e) {
        this.clearServerErrors(e);
        this.updateConversion(e);
    },
    displayHelpModal: function (e) {
        var image = $(e.currentTarget).data("img");
        var caption = $(e.currentTarget).data("caption");
        var html = "";
        if (image) {
            html += '<img src="' + image + '" class="img-responsive" />';
        }
        if (caption) {
            html += caption;
        }
        $("#helpModal .modal-body").html(html);
        $("#helpModal").modal();
    },
    updateMean: function (e) {
        var field = $(e.currentTarget).closest(".field").data("field");
        this.calculateMean(field);
    },
    triggerEqualize: function () {
        window.setTimeout(function () {
            $(window).trigger("pmi.equalize");
        }, 50);
    },
    calculateMean: function (field) {
        var fieldSelector = ".field-" + field;
        var secondThirdFields = ["blood-pressure-systolic", "blood-pressure-diastolic", "heart-rate"];
        var twoClosestFields = ["hip-circumference", "waist-circumference"];
        if ($.inArray(field, secondThirdFields) !== -1) {
            fieldSelector = ".field-" + field + "[data-replicate=2], .field-" + field + "[data-replicate=3]";
        }
        var values = [];
        this.$(fieldSelector)
            .find("input")
            .each(function () {
                if (parseFloat($(this).val())) {
                    values.push(parseFloat($(this).val()));
                }
            });
        if (values.length > 0) {
            if (values.length == 3 && $.inArray(field, twoClosestFields) !== -1) {
                values.sort(function (a, b) {
                    return a - b;
                });
                if (values[1] - values[0] < values[2] - values[1]) {
                    values.pop();
                } else if (values[2] - values[1] < values[1] - values[0]) {
                    values.shift();
                }
            }
            var sum = _.reduce(
                values,
                function (a, b) {
                    return a + b;
                },
                0
            );
            var mean = phpRound(sum / values.length, 1);
            this.$("#mean-" + field).html("<strong>" + mean + "</strong>");
            if (this.conversions[field]) {
                var converted = this.convert(this.conversions[field], mean);
                this.$("#convert-" + field).html("(" + converted + ")");
            }
        } else {
            this.$("#mean-" + field).text("--");
            this.$("#convert-" + field).text();
        }
    },
    calculateBmi: function () {
        var height = parseFloat(this.$("#form_height").val());
        var weight = parseFloat(this.$("#form_weight").val());
        if (height && weight) {
            var bmi = weight / ((height / 100) * (height / 100));
            bmi = phpRound(bmi, 1);
            this.$("#bmi").html("<strong>" + bmi + "</strong>");
        } else {
            this.$("#bmi").text("--");
        }
    },
    calculateCuff: function () {
        var circumference = parseFloat(this.$("#form_blood-pressure-arm-circumference").val());
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
    handlePregnantOrWheelchair: function () {
        var isPregnant = this.$("#form_pregnant").val() == 1;
        var isWheelchairBound = this.$("#form_wheelchair").val() == 1;
        if (isPregnant || isWheelchairBound) {
            this.$("#panel-hip-waist input").each(function () {
                $(this).valChange("");
            });
            this.$("#panel-hip-waist input, #panel-hip-waist select").each(function () {
                $(this).attr("disabled", true);
            });
            this.$("#hip-waist-skip").html('<span class="label label-danger">Skip</span>');
        }
        if (isPregnant) {
            this.$(".field-weight-prepregnancy").show();
            if (this.rendered) {
                this.$("#form_weight-protocol-modification").valChange("pregnancy");
            }
        }
        if (!isPregnant) {
            this.$("#form_weight-prepregnancy").valChange("");
            this.$(".field-weight-prepregnancy").hide();
            if (this.rendered && this.$("#form_weight-protocol-modification").val() == "pregnancy") {
                this.$("#form_weight-protocol-modification").valChange("");
            }
        }
        if (isWheelchairBound) {
            if (this.rendered) {
                this.$("#form_height-protocol-modification").valChange("wheelchair-bound");
                this.$("#form_weight-protocol-modification").valChange("wheelchair-bound");
            }
        }
        if (!isWheelchairBound) {
            if (this.rendered && this.$("#form_height-protocol-modification").val() == "wheelchair-bound") {
                this.$("#form_height-protocol-modification").valChange("");
            }
            if (this.rendered && this.$("#form_weight-protocol-modification").val() == "wheelchair-bound") {
                this.$("#form_weight-protocol-modification").valChange("");
            }
        }
        if (!isPregnant && !isWheelchairBound) {
            this.$("#panel-hip-waist input, #panel-hip-waist select").each(function () {
                $(this).attr("disabled", false);
            });
            this.$("#hip-waist-skip").text("");
        }
    },
    handleHeightProtocol: function () {
        if (this.$("#form_height-protocol-modification").val() == "refusal") {
            this.$("#form_height").valChange("").attr("disabled", true);
        } else {
            this.$("#form_height").attr("disabled", false);
        }
    },
    handleWeightProtocol: function () {
        var selected = this.$("#form_weight-protocol-modification").val();
        if (selected == "cannot-balance-on-scale" || selected == "refusal") {
            this.$("#form_weight").valChange("").attr("disabled", true);
        } else {
            this.$("#form_weight").attr("disabled", false);
        }
    },
    handleWaistProtocol: function () {
        if (this.$("#form_waist-circumference-protocol-modification").val() == "colostomy-bag") {
            this.$(".field-waist-circumference input").each(function () {
                $(this).valChange("").attr("disabled", true);
            });
        } else {
            var isPregnant = this.$("#form_pregnant").val() == 1;
            if (!isPregnant) {
                this.$(".field-waist-circumference input").each(function () {
                    $(this).attr("disabled", false);
                });
            }
        }
    },
    toggleThirdReading: function (field) {
        var first = parseFloat(this.$("#form_" + field + "_0").val());
        var second = parseFloat(this.$("#form_" + field + "_1").val());
        if (first > 0 && second > 0 && Math.abs(first - second) > 1) {
            this.$(".panel-" + field + "-3").show();
        } else {
            this.$(".panel-" + field + "-3").hide();
            this.$(".panel-" + field + "-3 input, .panel-" + field + "-3 select").each(function () {
                $(this).valChange("");
            });
        }
    },
    toggleThirdHipCircumference: function () {
        this.toggleThirdReading("hip-circumference");
    },
    toggleThirdWaistCircumference: function () {
        this.toggleThirdReading("waist-circumference");
    },
    calculateIrregularHeartRate: function () {
        var allIrregular = true;
        this.$(".field-irregular-heart-rate input").each(function () {
            if (!$(this).prop("checked")) {
                allIrregular = false;
            }
        });
        if (allIrregular) {
            $("#irregular-heart-rate-warning").html(
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
        } else {
            $("#irregular-heart-rate-warning").text("");
        }
    },
    checkDiastolic: function (e) {
        var replicate = $(e.currentTarget).closest(".form-group").data("replicate");
        var systolic = parseFloat(
            this.$(".field-blood-pressure-systolic[data-replicate=" + replicate + "] input").val()
        );
        var diastolic = parseFloat(
            this.$(".field-blood-pressure-diastolic[data-replicate=" + replicate + "] input").val()
        );
        var container = this.$(".field-blood-pressure-diastolic[data-replicate=" + replicate + "]").closest(
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
    },
    clearServerErrors: function () {
        this.$("span.help-block ul li").remove();
    },
    kgToLb: function (kg) {
        return phpRound(parseFloat(kg) * 2.2046, 1);
    },
    cmToIn: function (cm) {
        return phpRound(parseFloat(cm) * 0.3937, 1);
    },
    convert: function (type, val) {
        switch (type) {
            case "in":
                return this.cmToIn(val) + " in";
            case "ftin":
                var inches = this.cmToIn(val);
                var feet = Math.floor(inches / 12);
                inches = phpRound(inches % 12);
                return feet + "ft " + inches + "in";
            case "lb":
                return this.kgToLb(val) + " lb";
            default:
                return false;
        }
    },
    updateConversion: function (e) {
        var field = $(e.currentTarget).closest(".field").data("field");
        this.calculateConversion(field);
    },
    calculateConversion: function (field) {
        var input = this.$(".field-" + field).find("input");
        if (input.length > 1) {
            // replicate conversions are handled in calculateMean method
            return;
        }
        if (this.conversions[field]) {
            var val = parseFloat(input.val());
            if (val) {
                var converted = this.convert(this.conversions[field], val);
                if (converted) {
                    this.$("#convert-" + field).text("(" + converted + ")");
                } else {
                    this.$("#convert-" + field).text("");
                }
            } else {
                this.$("#convert-" + field).text("");
            }
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
        return (
            (warning.min && val < warning.min) ||
            (warning.max && val > warning.max) ||
            (warning.between && val > warning.between[0] && val < warning.between[1])
        );
    },
    displayWarnings: function () {
        var self = this;
        _.each(this.warnings, function (warnings, field) {
            this.$(".field-" + field)
                .find("input, select")
                .each(function () {
                    var input = $(this);
                    var field = input.closest(".field").data("field");
                    var container = input.closest(".form-group");
                    container.next(".metric-warnings").remove();
                    if (container.find(".metric-errors div").length > 0) {
                        return;
                    }
                    var val = input.val();
                    $.each(warnings, function (key, warning) {
                        if (!warning.consecutive && self.warningConditionMet(warning, val)) {
                            container.after($('<div class="metric-warnings text-warning">').text(warning.message));
                            return false; // only show first (highest priority) warning
                        }
                    });
                });
        });
    },
    displayConsecutiveWarning: function (field, e) {
        var self = this;
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
        var values = [];
        this.$(".field-" + field + " input").each(function () {
            values.push($(this).val());
        });
        var warned = false;
        $.each(this.warnings[field], function (key, warning) {
            if (!warning.consecutive) {
                return false;
            }
            var consecutiveConditionsMet = 0;
            var isConsecutive = false;
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
                    var input = $(e.currentTarget);
                    new PmiConfirmModal({
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
        var self = this;
        var input = $(e.currentTarget);
        var field = input.closest(".field").data("field");
        var container = input.closest(".form-group");
        container.next(".metric-warnings").remove();
        if (container.find(".metric-errors div").length > 0) {
            return;
        }
        var val = input.val();
        if (this.warnings[field]) {
            var warned = false;
            $.each(this.warnings[field], function (key, warning) {
                if (!warning.consecutive && self.warningConditionMet(warning, val)) {
                    if (warning.alert) {
                        new PmiConfirmModal({
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
                    container.after($('<div class="metric-warnings text-warning">').text(warning.message));
                    return false; // only show first (highest priority) warning
                }
            });
        }
    },
    initialize: function (obj) {
        this.warnings = obj.warnings;
        this.conversions = obj.conversions;
        this.rendered = false;
        this.render();
    },
    render: function () {
        var self = this;
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

        var processedReplicates = {};
        this.$(".replicate .field").each(function () {
            var field = $(this).data("field");
            if (!processedReplicates[field]) {
                self.calculateMean(field);
                self.displayConsecutiveWarning(field);
                processedReplicates[field] = true;
            }
        });

        _.each(_.keys(this.conversions), function (field) {
            self.calculateConversion(field);
        });
        this.displayWarnings();
        this.calculateBmi();
        this.calculateCuff();
        this.calculateIrregularHeartRate();
        this.handlePregnantOrWheelchair();
        this.handleHeightProtocol();
        this.handleWeightProtocol();
        this.handleWaistProtocol();
        this.toggleThirdHipCircumference();
        this.toggleThirdWaistCircumference();
        this.triggerEqualize();
        this.rendered = true;
        return this;
    }
});
