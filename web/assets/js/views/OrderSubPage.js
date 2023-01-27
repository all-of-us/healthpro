/**
 * Order sub page form view
 */
PMI.views["OrderSubPage"] = Backbone.View.extend({
    events: {
        "click .toggle-help-image": "displayHelpModal",
        "click #unlock-order": "displayUnlockWarningModal"
    },
    displayHelpModal: function (e) {
        this.$("#orderHelpModal").modal();
    },
    displayUnlockWarningModal: function (e) {
        var url = $(e.currentTarget).data("href");
        $("#unlock-continue").attr("href", url);
        this.$("#unlockWarningModal").modal();
    },
    initialize: function () {
        this.render();
    },
    render: function () {
        return this;
    }
});
