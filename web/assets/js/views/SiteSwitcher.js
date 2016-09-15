/**
 * Control that allows the user to switch sites.
 */

(function ($) { // BEGIN wrapper

var SiteSwitcher = Backbone.View.extend({
    events: {
        "click .site-submit": "switchSite"
    },
    switchSite: function() {
        var siteId = this.$(".site-id").val();
        if (_.truthy(siteId)) {
            var path = PMI.path.switchSite.replace("SITE_ID", siteId);
            window.location = path;
        }
        return true;
    },
    initialize: function() { this.render(); },
    render: function() {
        return this;
    }
});

// initialize the view if the modal is present
$(document).ready(function() {
    if ($("#siteModal").length > 0) {
        new SiteSwitcher({el: $("#siteModal") });
    }
});

})(jQuery); // END wrapper
