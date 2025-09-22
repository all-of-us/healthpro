const _ = require("underscore");

/**
 * Physical evaluation form view
 */

/* eslint security/detect-object-injection: "off" */

PMI.views["PhysicalEvaluation-0.3-blood-donor"] = Backbone.View.extend({
    events: {
        "click .toggle-help-image": "displayHelpModal",
        "change input, select": "inputChange",
        "keyup input": "inputKeyup",
        "change #form_blood-pressure-arm-circumference": "calculateCuff",
        "keyup #form_blood-pressure-arm-circumference": "calculateCuff",
        "change .field-irregular-heart-rate input": "calculateIrregularHeartRate",
        "change #form_pregnant, #form_wheelchair": "handlePregnantOrWheelchair",
        "change #form_weight-protocol-modification": "handleWeightProtocol",
        "change .field-blood-pressure-diastolic input,  .field-blood-pressure-systolic input": "checkDiastolic",
        "click .modification-toggle a": "showModification",
        "change .modification-select select": "handleProtocolModification",
        "click .autofill-protocol-modification": "autofillProtocolModification",
        "click .alt-units-toggle a": "enableAltUnits",
        "click .alt-units-field a": "cancelAltUnits",
        "keyup .alt-units-field input": "convertAltUnits",
        "change .alt-units-field input": "convertAltUnits"
    },
    inputChange: function (e) {
        this.clearServerErrors(e);
        this.displayWarning(e);
        this.updateConversion(e);
        this.triggerEqualize();
    },
    inputKeyup: function (e) {
        this.updateConversion(e);
    },
    displayHelpModal: function (e) {
        let id = $(e.currentTarget).data("id");
        let html = $("#" + id).html();
        $("#helpModalBs5 .modal-body").html(html);
        let helpModal = new bootstrap.Modal($("#helpModalBs5")[0]);
        helpModal.show();
    },
    triggerEqualize: function () {
        window.setTimeout(function () {
            $(window).trigger("pmi.equalize");
        }, 50);
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
        var isWheelchairUser = this.$("#form_wheelchair").val() == 1;
        if (isPregnant) {
            this.$(".field-weight-prepregnancy").show();
            this.$(".field-weight-prepregnancy").next(".alt-units-block").show();
            if (this.rendered) {
                this.$("#form_weight-protocol-modification").valChange("pregnancy");
            }
        }
        if (!isPregnant) {
            this.$("#form_weight-prepregnancy").valChange("");
            this.$(".field-weight-prepregnancy").hide();
            this.$(".field-weight-prepregnancy").next(".alt-units-block").hide();
            if (this.rendered && this.$("#form_weight-protocol-modification").val() == "pregnancy") {
                this.$("#form_weight-protocol-modification").valChange("");
            }
        }
        if (isWheelchairUser) {
            if (this.rendered) {
                this.$("#form_weight-protocol-modification").valChange("wheelchair-user");
            }
        }
        if (!isWheelchairUser) {
            if (this.rendered && this.$("#form_weight-protocol-modification").val() == "wheelchair-user") {
                this.$("#form_weight-protocol-modification").valChange("");
            }
        }
    },
    handleWeightProtocol: function () {
        var selected = this.$("#form_weight-protocol-modification").val();
        if (selected === "cannot-balance-on-scale" || selected === "refusal" || selected === "pandemic") {
            this.$("#form_weight, #form_weight-prepregnancy").each(function () {
                $(this).valChange("").attr("disabled", true);
            });
            this.$(".field-weight, .field-weight-prepregnancy").each(function () {
                $(this).next(".alt-units-block").hide();
            });
        } else {
            if (!this.finalized) {
                this.$("#form_weight").attr("disabled", false);
                this.$(".field-weight").next(".alt-units-block").show();
                if (this.$("#form_pregnant").val() == 1) {
                    this.$("#form_weight-prepregnancy").attr("disabled", false);
                    this.$(".field-weight-prepregnancy").next(".alt-units-block").show();
                }
            }
        }
        if (selected === "other") {
            this.$(".field-weight-protocol-modification-notes").parent().show();
        } else {
            this.$(".field-weight-protocol-modification-notes").parent().hide();
            this.$("#form_weight-protocol-modification-notes").val("");
        }
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
    clearServerErrors: function (e) {
        const $input = $(e.currentTarget);
        $input.removeClass("is-invalid");
        const $field = $input.closest(".field");
        $field.find("div.invalid-feedback ul li").remove();
    },
    kgToLb: function (kg) {
        return phpRound(parseFloat(kg) * 2.2046, 1);
    },
    cmToIn: function (cm) {
        return phpRound(parseFloat(cm) * 0.3937, 1);
    },
    lbToKg: function (lb) {
        return phpRound(parseFloat(lb) / 2.2046, 1);
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
    calculateConversion: function (field, index = null) {
        var input = this.$(".field-" + field).find("input");
        if ($.inArray(field, this.hipWaistFields) === -1 && input.length > 1) {
            // replicate conversions are handled in calculateMean method except for hip & waist circumference fields
            return;
        }
        var convertFieldId = "#convert-" + field;
        if (index !== null) {
            input = this.$("#form_" + field + "_" + index);
            convertFieldId = "#convert-" + field + "_" + index;
        }
        let val = null;
        if (this.recordUserValues[field]) {
            if (field === "height") {
                let feet = parseFloat($(`#form_height-ft-user-entered`).val());
                let inches = parseFloat($(`#form_height-in-user-entered`).val());
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
                    container.find(".metric-warnings").remove();
                    if (container.find(".metric-errors div").length > 0) {
                        return;
                    }
                    var val = input.val();
                    $.each(warnings, function (key, warning) {
                        if (!warning.consecutive && self.warningConditionMet(warning, val)) {
                            container.append($('<div class="metric-warnings text-warning">').text(warning.message));
                            return false; // only show first (highest priority) warning
                        }
                    });
                });
        });
    },
    displayWarning: function (e) {
        var self = this;
        var input = $(e.currentTarget);
        var field = input.closest(".field").data("field");
        var container = input.closest(".form-group");
        container.find(".metric-warnings").remove();
        if (container.find(".metric-errors div").length > 0) {
            return;
        }
        var val = input.val();
        if (this.warnings[field]) {
            $.each(this.warnings[field], function (key, warning) {
                if (!warning.consecutive && self.warningConditionMet(warning, val)) {
                    container.append($('<div class="metric-warnings text-warning">').text(warning.message));
                    return false; // only show first (highest priority) warning
                }
            });
        }
    },
    handleProtocolModification: function (e) {
        var block = $(e.currentTarget).closest(".modification-block");
        this.handleProtocolModificationBlock(block);
    },
    handleProtocolModificationBlock: function (block) {
        var modification = block.find(".modification-select select").val();
        var manualMeasurement = block.find(".modification-manual input:checkbox").is(":checked");
        var self = this;
        if (modification === "" && manualMeasurement === false) {
            block.find(".modification-select").hide();
            block.find(".modification-toggle").show();
        } else {
            block.find(".modification-toggle").hide();
            block.find(".modification-select").show();
        }
        if (modification === "refusal" || modification === "pandemic" || modification === "colostomy-bag") {
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
        var block = $(e.currentTarget).closest(".modification-block");
        this.showModificationBlock(block);
        this.triggerEqualize();
    },
    showModifications: function () {
        var self = this;
        this.$(".modification-block").each(function () {
            self.handleProtocolModificationBlock($(this));
        });
    },
    autofillProtocolModification: function (e) {
        var self = this;
        var reason = $(e.currentTarget).data("reason");
        this.$(".modification-block").each(function () {
            var modification = $(this).find(".modification-select select").val();
            if (!modification) {
                var needsModification = false;
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
        if (!$("#form_weight").val() && !$("#form_weight-protocol-modification").val()) {
            $("#form_weight-protocol-modification").val(reason);
        }
        self.handleWeightProtocol();
    },
    enableAltUnits: function (e) {
        var block = $(e.currentTarget).closest(".alt-units-block");
        block.find(".alt-units-field").show();
        block.find(".alt-units-toggle").hide();
        block.prev().find("input").attr("readonly", true);
        this.triggerEqualize();
    },
    cancelAltUnits: function (e) {
        var block = $(e.currentTarget).closest(".alt-units-block");
        block.find(".alt-units-toggle").show();
        block.find(".alt-units-field").hide();
        block.prev().find("input").attr("readonly", false);
        block.find(".alt-units-field input").val("");
        this.triggerEqualize();
    },
    convertAltUnits: function (e) {
        var block = $(e.currentTarget).closest(".alt-units-field");
        var val;
        var unit = block.find(".input-group-text").text();
        val = block.find("input").val();
        if (unit == "lb") {
            val = this.lbToKg(val);
        }
        if (isNaN(val)) {
            val = "";
        }
        var input = block.parent().prev().find("input");
        input.val(val);
        if (e.type == "change") {
            block.parent().prev().find("input").trigger("change"); // trigger change even if not different
            block.parent().prev().find("input").parsley().validate(); // trigger parsley validation
        }
    },
    initParsley: function () {
        self = this;

        this.$("form").parsley({
            errorClass: "has-error",
            classHandler: function (el) {
                return el.$element.closest(".input-group");
            },
            errorsContainer: function (el) {
                return el.$element.closest(".input-group");
            },
            errorsWrapper: '<div class="metric-errors help-block filled w-100 mt-2"></div>',
            errorTemplate: "<div></div>",
            trigger: "keyup change"
        });
    },
    hideWholeBloodModification: function () {
        // Whole blood donor modification is only available if set initially
        // from donor check. So if it isn't selected, hide the option
        if (this.$("#form_weight-protocol-modification").val() !== "whole-blood-donor") {
            this.$('#form_weight-protocol-modification option[value="whole-blood-donor"]').hide();
        }
    },
    initialize: function (obj) {
        this.warnings = obj.warnings;
        this.conversions = obj.conversions;
        this.recordUserValues = obj.recordUserValues;
        this.finalized = obj.finalized;
        this.rendered = false;
        this.render();
    },
    render: function () {
        var self = this;
        self.initParsley();

        var processedReplicates = {};
        this.$(".replicate .field").each(function () {
            var field = $(this).data("field");
            if (!processedReplicates[field]) {
                processedReplicates[field] = true;
            }
        });

        _.each(_.keys(this.conversions), function (field) {
            self.calculateConversion(field);
        });
        this.showModifications();
        this.displayWarnings();
        this.calculateCuff();
        this.calculateIrregularHeartRate();
        this.handlePregnantOrWheelchair();
        this.handleWeightProtocol();
        this.hideWholeBloodModification();
        if (this.finalized) {
            this.$(".modification-toggle").hide();
            this.$(".alt-units-block").hide();
        }
        this.triggerEqualize();
        this.rendered = true;
        return this;
    }
});
