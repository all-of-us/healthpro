/**
 * Create new biobank order
 */

(function ($) { // BEGIN wrapper

var CreateOrder = Backbone.View.extend({
    events: {
        "click #customize-enable": "enableCustomize",
        "click #customize-disable": "disableCustomize"
    },
    enableCustomize: function(e) {
        this.$('#customize-on').show();
        this.$('#customize-off').hide();
        window.equalizePanelHeight('.row-equal-height');
        e.preventDefault();
    },
    disableCustomize: function(e) {
        this.$('#customize-off').show();
        this.$('#customize-on').hide();
        window.equalizePanelHeight('.row-equal-height');
        e.preventDefault();
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
});

})(jQuery); // END wrapper
