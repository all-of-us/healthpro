/**
 * Create new biobank order
 */

(function ($) { // BEGIN wrapper

var CreateOrder = Backbone.View.extend({
    events: {
        "click #customize-enable": "enableCustomize",
        "click #customize-disable": "disableCustomize",
        "click .toggle-help-image": "displayHelpModal"
    },
    enableCustomize: function(e) {
        this.$('#customize-on').show();
        this.$('#customize-off').hide();
        $(window).trigger('pmi.equalize');
        e.preventDefault();
    },
    disableCustomize: function(e) {
        this.$('#customize-off').show();
        this.$('#customize-on').hide();
        $(window).trigger('pmi.equalize');
        e.preventDefault();
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
        this.$('#helpModal .modal-body').html(html);
        this.$('#helpModal').modal();
    },
    initialize: function() { this.render(); },
    render: function() {
        return this;
    }
});

$(document).ready(function() {
    if ($("#createOrder").length > 0) {
        new CreateOrder({el: $("#createOrder") });
    }
    $('button.reportKit').click(function() {
        var location = $(this).data('href');
        new PmiConfirmModal({
            title: 'Attention',
            msg: 'You are leaving the DRC HealthPro web application and accessing a non-FISMA destination. *Entering of participant information is prohibited at the destination.*This external link provides additional information that is consistent with the intended purpose of HealthPro. DRC cannot attest to the accuracy of a non-DRC site. <br/><br/> Linking to a non-DRC site does not constitute endorsement by DRC or any of its employees of the sponsors or information and products presented on the site. You will be subject to the destination site\'s privacy policy when you follow the link.',
            isHTML: true,
            onTrue: function() {
                window.open(location, '_blank');
            },
            btnTextTrue: 'Continue'
        });
    });
});

})(jQuery); // END wrapper
