/**
 * Physical evaluation form view
 */
PMI.views['PhysicalEvaluation-0.2'] = Backbone.View.extend({
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
        "change #form_pregnant, #form_wheelchair": "togglePregnantOrWheelchair"
    },
    inputChange: function(e) {
        this.clearServerErrors(e);
        this.displayWarnings(e);
        this.updateConversion(e);
        this.triggerEqualize();
    },
    inputKeyup: function(e) {
        this.clearServerErrors(e);
        this.updateConversion(e);
    },
    displayHelpModal: function(e) {
        var image = $(e.currentTarget).data('img');
        var caption = $(e.currentTarget).data('caption');
        var html = '';
        if (image) {
            html += '<img src="' + image + '" class="img-responsive" />';
        }
        if (caption) {
            html += caption;
        }
        $('#helpModal .modal-body').html(html);
        $('#helpModal').modal();
    },
    updateMean: function(e) {
        var field = $(e.currentTarget).closest('.field').data('field');
        this.calculateMean(field);
    },
    triggerEqualize: function() {
        window.setTimeout(function() {
            $(window).trigger('pmi.equalize');
        }, 50);
    },
    calculateMean: function(field) {
        var sum = 0;
        var count = 0;
        this.$('.field-' + field).find('input').each(function() {
            if (parseFloat($(this).val())) {
                sum += parseFloat($(this).val());
                count++;
            }
        });
        if (count > 0) {
            var mean = (sum / count).toFixed(1);
            this.$('#mean-' + field).html('<strong>' + mean + '</strong>');
            if (this.conversions[field]) {
                var converted = this.convert(this.conversions[field], mean);
                this.$('#convert-' + field).html('('+converted+')');
            }
        } else {
            this.$('#mean-' + field).text('--');
            this.$('#convert-' + field).text();
        }
    },
    calculateBmi: function() {
        var height = parseFloat(this.$('#form_height').val());
        var weight = parseFloat(this.$('#form_weight').val());
        if (height && weight) {
            var bmi = weight / ((height/100) * (height/100));
            bmi = bmi.toFixed(1);
            this.$('#bmi').html('<strong>' + bmi + '</strong>');
        } else {
            this.$('#bmi').text('--');
        }
    },
    calculateCuff: function() {
        var circumference = parseFloat(this.$('#form_blood-pressure-arm-circumference').val());
        if (!circumference || circumference < 22 || circumference > 52) {
            this.$('#cuff-size').text('--');
        } else if (circumference < 27) {
            this.$('#cuff-size').text('Small adult (12×22 cm)');
        } else if (circumference < 35) {
            this.$('#cuff-size').text('Adult (16×30 cm)');
        } else if (circumference < 45) {
            this.$('#cuff-size').text('Large adult (16×36 cm)');
        } else {
            this.$('#cuff-size').text('Adult thigh (16×42 cm)');
        }
    },
    togglePregnantOrWheelchair: function() {
        var isPregnant = (this.$('#form_pregnant').val() == 1);
        var isWheelchairBound = (this.$('#form_wheelchair').val() == 1);
        if (isPregnant || isWheelchairBound) {
            this.$('#panel-hip-waist input, #panel-hip-waist select').each(function() {
                $(this).val('');
                $(this).attr('disabled', true);
            });
            this.$('#hip-waist-skip').html('<span class="label label-danger">Skip</span>');
        }
        if (isPregnant) {
            this.$('.field-weight-prepregnancy').show();
            this.$('#form_weight-protocol-modification').val(3);
            this.$('#pregnant-message').html('<p class="text-danger">Pregnant women should be measured for both height and weight. Do not measure the hip and waist of pregnant participants.</p>');
        }
        if (!isPregnant) {
            this.$('#form_weight-prepregnancy').val('');
            this.$('.field-weight-prepregnancy').hide();
            if (this.$('#form_weight-protocol-modification').val() == 3) {
                this.$('#form_weight-protocol-modification').val(0);
            }
            this.$('#pregnant-message').text('');
        }
        if (isWheelchairBound) {
            this.$('#wheelchair-message').html('<p class="text-danger">Please record estimated participant height and weight in the "Height" and "Weight" fields. Do not measure the hip and waist of wheelchair bound participants.</p>');
        }
        if (!isWheelchairBound) {
            this.$('#wheelchair-message').text('');
        }
        if (!isPregnant && !isWheelchairBound) {
            this.$('#panel-hip-waist input, #panel-hip-waist select').each(function() {
                $(this).attr('disabled', false);
            });
            this.$('#hip-waist-skip').text('');
        }
    },
    clearServerErrors: function() {
        this.$('span.help-block ul li').remove();
    },
    kgToLb: function(kg) {
        return (parseFloat(kg) * 2.2046).toFixed(1);
    },
    cmToIn: function(cm) {
        return (parseFloat(cm) * 0.3937).toFixed(1);
    },
    convert: function(type, val) {
        switch (type) {
            case 'in':
                return this.cmToIn(val) + ' in';
            case 'ftin':
                var inches = this.cmToIn(val);
                var feet = Math.floor(inches / 12);
                inches = (inches % 12).toFixed();
                return feet + 'ft ' + inches + 'in';
            case 'lb':
                return this.kgToLb(val) + ' lb';
            default:
                return false;
        }
    },
    updateConversion: function(e) {
        var field = $(e.currentTarget).closest('.field').data('field');
        this.calculateConversion(field);
    },
    calculateConversion: function(field) {
        var input = this.$('.field-' + field).find('input');
        if (input.length > 1) {
            // replicate conversions are handled in calculateMean method
            return;
        }
        var field = input.closest('.field').data('field');
        if (this.conversions[field]) {
            var val = parseFloat(input.val());
            if (val) {
                var converted = this.convert(this.conversions[field], val);
                if (converted) {
                    this.$('#convert-' + field).text('('+converted+')');
                } else {
                    this.$('#convert-' + field).text('');
                }
            } else {
                this.$('#convert-' + field).text('');
            }
        }
    },
    displayWarnings: function(e) {
        var input = $(e.currentTarget);
        var field = input.closest('.field').data('field');
        var container = input.closest('.form-group');
        container.find('.metric-warnings').remove();
        if (container.find('.metric-errors div').length > 0) {
            return;
        }
        var val = parseFloat(input.val());
        if (!val) {
            return;
        }
        if (this.warnings[field]) {
            var modalDisplayed = false;
            _.each(this.warnings[field], function(warning) {
                if ((warning.min && val < warning.min) ||
                    (warning.max && val > warning.max))
                {
                    if (warning.alert) {
                        if (!modalDisplayed) {
                            new PmiConfirmModal({
                                msg: warning.message,
                                onFalse: function() {
                                    input.val('');
                                    input.focus();
                                    input.trigger('change');
                                },
                                btnTextTrue: 'Confirm value and take action',
                                btnTextFalse: 'Clear value and reenter'
                            });
                            modalDisplayed = true;
                        }
                    } else {
                        container.append($('<div class="metric-warnings text-danger">').text(warning.message));
                    }
                }
            });
        }
    },
    initialize: function(obj) {
        this.warnings = obj.warnings;
        this.conversions = obj.conversions;
        this.render();
    },
    render: function() {
        var self = this;
        this.$('form').parsley({
            errorClass: "has-error",
            classHandler: function(el) {
                return el.$element.closest(".form-group");
            },
            errorsContainer: function(el) {
                return el.$element.closest(".form-group");
            },
            errorsWrapper: '<div class="metric-errors help-block"></div>',
            errorTemplate: '<div></div>',
            trigger: "keyup change"
        });
        this.$('.replicate .field').each(function() {
            var field = $(this).data('field');
            self.calculateMean(field);
        });
        _.each(_.keys(this.conversions), function(field) {
            self.calculateConversion(field);
        });
        self.calculateBmi();
        self.calculateCuff();
        self.togglePregnantOrWheelchair();
        return this;
    }
});
