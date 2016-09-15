/**
 * Physical evaluation form view
 */

(function ($) { // BEGIN wrapper

var PhysicalEvaluation = Backbone.View.extend({
    events: {
        "click .help-image": "displayHelpModal"
    },
    displayHelpModal: function(e) {
        var image = $(e.target).attr('src');
        $('#imageModal .modal-body').html('<img src="' + image + '" class="img-responsive" />');
        $('#imageModal').modal();
    },
    initialize: function() { this.render(); },
    render: function() {
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
