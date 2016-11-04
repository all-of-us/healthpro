/**
 * Order sub page form view
 */
PMI.views['OrderSubPage'] = Backbone.View.extend({
    events: {
        "click .toggle-help-image": "displayHelpModal",
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
