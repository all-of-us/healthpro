/**
 * Order sub page form view
 */
PMI.views['OrderSubPage'] = Backbone.View.extend({
    events: {
        "click .toggle-help-image": "displayHelpModal",
        "click #unlock-order": "displayUnlockWarningModal",
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
    displayUnlockWarningModal: function (e) {
        var url = $(e.currentTarget).data('href');
        $('#unlock-continue').attr('href', url);
        this.$('#unlockWarningModal').modal();
    },
    initialize: function() { this.render(); },
    render: function() {
        return this;
    }
});
