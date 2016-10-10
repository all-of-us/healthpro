/**
 * Physical evaluation form view
 */
PMI.views['PhysicalEvaluation'] = Backbone.View.extend({
    events: {
        "click .toggle-help-image": "displayHelpModal",
        "change .replicate input": "updateMean",
        "keyup .replicate input": "updateMean",
        "change input": "clearServerErrors",
        "keyup input": "clearServerErrors",
        "change input": "displayWarnings"
    },
    displayHelpModal: function(e) {
        var image = $(e.currentTarget).data('img');
        this.$('#imageModal .modal-body').html('<img src="' + image + '" class="img-responsive" />');
        this.$('#imageModal').modal();
    },
    updateMean: function(e) {
        var field = $(e.currentTarget).closest('.field').data('field');
        this.calculateMean(field);
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
            this.$('#mean-' + field).html('<span class="label label-default">Average: ' + mean + '</span>');
        } else {
            this.$('#mean-' + field).text('');
        }
    },
    clearServerErrors: function() {
        this.$('span.help-block ul li').remove();
    },
    displayWarnings: function(e) {
        var field = $(e.currentTarget).closest('.field').data('field');
        var container = $(e.currentTarget).closest('.form-group');
        container.find('.metric-warnings').remove();
        if (container.find('.metric-errors div').length > 0) {
            return;
        }
        var val = parseFloat($(e.currentTarget).val());
        if (!val) {
            return;
        }
        if (this.warnings[field]) {
            _.each(this.warnings[field], function(warning) {
                if ((warning.min && val < warning.min) ||
                    (warning.max && val > warning.max))
                {
                    container.append($('<div class="metric-warnings text-warning">').text(warning.message));
                }
            });
        }
    },
    initialize: function(obj) {
        this.render();
        this.warnings = obj.warnings
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
        this.$('.field').each(function() {
            var field = $(this).data('field');
            if ($(this).find('.mean').length > 0) {
                self.calculateMean(field);
            }
        });
        return this;
    }
});
