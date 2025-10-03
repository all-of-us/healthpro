const _ = require("underscore");

/**
 * Physical evaluation form view
 */

/* eslint security/detect-object-injection: "off" */

PMI.views["PhysicalEvaluation-0.1"] = Backbone.View.extend({
    events: {
        "click .toggle-help-image": "displayHelpModal",
        "change .replicate input": "updateMean",
        "keyup .replicate input": "updateMean",
        "change input": "inputChange",
        "keyup input": "inputKeyup",
        "keyup #form_height, #form_weight": "calculateBmi",
        "change #form_height, #form_weight": "calculateBmi"
    },
    inputChange: function (e) {
        this.clearServerErrors(e);
        this.displayWarnings(e);
        this.updateConversion(e);
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
    calculateMean: function (field) {
        var sum = 0;
        var count = 0;
        this.$(".field-" + field)
            .find("input")
            .each(function () {
                if (parseFloat($(this).val())) {
                    sum += parseFloat($(this).val());
                    count++;
                }
            });
        if (count > 0) {
            var mean = phpRound(sum / count, 1);
            this.$("#mean-" + field).html('<span class="label label-primary">Average: ' + mean + "</span>");
            if (this.conversions[field]) {
                var converted = this.convert(this.conversions[field], mean);
                this.$("#convert-" + field).html("<small>(" + converted + ")</small>");
            }
        } else {
            this.$("#mean-" + field).text("");
            this.$("#convert-" + field).text("");
        }
    },
    calculateBmi: function () {
        var height = parseFloat(this.$("#form_height").val());
        var weight = parseFloat(this.$("#form_weight").val());
        if (height && weight) {
            var bmi = weight / ((height / 100) * (height / 100));
            bmi = phpRound(bmi, 1);
            this.$("#bmi").html('<span class="label label-primary">BMI: ' + bmi + "</span>");
        } else {
            this.$("#bmi").html("");
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
        if (input.closest(".replicate").length > 0) {
            // replicate conversions are handled in calculateMean method
            return;
        }
        var field = input.closest(".field").data("field");
        if (this.conversions[field]) {
            var val = parseFloat(input.val());
            if (val) {
                var converted = this.convert(this.conversions[field], val);
                if (converted) {
                    this.$("#convert-" + field).html("<small>(" + converted + ")</small>");
                } else {
                    this.$("#convert-" + field).html("");
                }
            } else {
                this.$("#convert-" + field).html("");
            }
        }
    },
    displayWarnings: function (e) {
        var input = $(e.currentTarget);
        var field = input.closest(".field").data("field");
        var container = input.closest(".field");
        container.next(".metric-warnings").remove();
        if (container.find(".metric-errors div").length > 0) {
            return;
        }
        var val = parseFloat(input.val());
        if (!val) {
            return;
        }
        if (this.warnings[field]) {
            _.each(this.warnings[field], function (warning) {
                if ((warning.min && val < warning.min) || (warning.max && val > warning.max)) {
                    if (warning.alert) {
                        new PmiConfirmModal({
                            msg: warning.message,
                            onFalse: function () {
                                input.val("");
                                input.focus();
                                input.trigger("change");
                            },
                            btnTextTrue: "Confirm value and seek medical attention",
                            btnTextFalse: "Clear value and reenter"
                        });
                    } else {
                        container.after($('<div class="metric-warnings text-danger">').text(warning.message));
                    }
                }
            });
        }
    },
    initialize: function (obj) {
        this.warnings = obj.warnings;
        this.conversions = obj.conversions;
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
        this.$(".field").each(function () {
            var field = $(this).data("field");
            if ($(this).find(".mean").length > 0) {
                self.calculateMean(field);
            }
        });
        _.each(_.keys(this.conversions), function (field) {
            self.calculateConversion(field);
        });
        self.calculateBmi();
        return this;
    }
});
