/**
 * Physical evaluation form view
 */

(function ($) { // BEGIN wrapper

var PhysicalEvaluation = Backbone.View.extend({
    events: {
        "click .help-image": "displayHelpModal",
        "change .replicate input": "updateMean",
        "keyup .replicate input": "updateMean"
    },
    displayHelpModal: function(e) {
        var image = $(e.target).attr('src');
        $('#imageModal .modal-body').html('<img src="' + image + '" class="img-responsive" />');
        $('#imageModal').modal();
    },
    updateMean: function(e) {
        var field = $(e.target).closest('.field').data('field');
        this.calculateMean(field);
    },
    calculateMean: function(field) {
        var sum = 0;
        var count = 0;
        $('.field-' + field).find('input').each(function() {
            if (parseFloat($(this).val())) {
                sum += parseFloat($(this).val());
                count++;
            }
        });
        if (count > 0) {
            var mean = (sum / count).toFixed(1);
            $('#mean-' + field).html('<span class="label label-default">Average: ' + mean + '</span>');
        } else {
            $('#mean-' + field).text('');
        }
    },
    initialize: function() { this.render(); },
    render: function() {
        var self = this;
        $('.field').each(function() {
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
