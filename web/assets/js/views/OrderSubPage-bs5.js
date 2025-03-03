/**
 * Order sub page form view
 */
PMI.views["OrderSubPage"] = Backbone.View.extend({
    events: {
        "click .toggle-help-image": "displayHelpModal",
        "click #unlock-order": "displayUnlockWarningModal"
    },
    displayHelpModal: function (e) {
        let helpModal = new bootstrap.Modal(this.$("#orderHelpModal")[0]);
        helpModal.show();
    },
    displayUnlockWarningModal: function (e) {
        let url = $(e.currentTarget).data("href");
        $("#unlock-continue").attr("href", url);
        let unlockModal = new bootstrap.Modal(this.$("#unlockWarningModal")[0]);
        unlockModal.show();
    },
    initialize: function () {
        this.render();
    },
    render: function () {
        return this;
    }
});
