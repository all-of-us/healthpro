/**
 * Physical evaluation form view
 */

(function ($) { // BEGIN wrapper

var PhysicalEvaluation = Backbone.View.extend({
    events: {
        "click .help-image": "displayHelpModal",
        "change .replicate input": "updateMean",
        "keyup .replicate input": "updateMean",
        "change input": "clearServerErrors",
        "keyup input": "clearServerErrors"
    },
    displayHelpModal: function(e) {
        var image = $(e.target).attr('src');
        this.$('#imageModal .modal-body').html('<img src="' + image + '" class="img-responsive" />');
        this.$('#imageModal').modal();
    },
    updateMean: function(e) {
        var field = $(e.target).closest('.field').data('field');
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
    initialize: function() { this.render(); },
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
            errorsWrapper: '<span class="help-block"></span>',
            errorTemplate: '<span></span>',
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

// initialize the view if the modal is present
$(document).ready(function() {
    if ($("#physicalEvaluation").length > 0) {
        new PhysicalEvaluation({el: $("#physicalEvaluation") });
    }
});

})(jQuery); // END wrapper
