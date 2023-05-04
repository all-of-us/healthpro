/**
 * Create new biobank order
 */

(function ($) {
    // BEGIN wrapper

    var CreateOrder = Backbone.View.extend({
        events: {
            "click #customize-enable": "enableCustomize",
            "click #customize-disable": "disableCustomize",
            "click .toggle-help-image": "displayHelpModal"
        },
        enableCustomize: function (e) {
            this.$("#customize-on").show();
            this.$("#customize-off").hide();
            $(window).trigger("pmi.equalize");
            e.preventDefault();
        },
        disableCustomize: function (e) {
            this.$("#customize-off").show();
            this.$("#customize-on").hide();
            $(window).trigger("pmi.equalize");
            e.preventDefault();
        },
        displayHelpModal: function (e) {
            var image = $(e.currentTarget).data("img");
            var caption = $(e.currentTarget).data("caption");
            var html = "";
            if (image) {
                html += '<img src="' + image + '" class="img-responsive" />';
            }
            if (caption) {
                html += caption;
            }
            this.$("#helpModal .modal-body").html(html);
            this.$("#helpModal").modal();
        },
        initialize: function () {
            this.render();
        },
        render: function () {
            return this;
        }
    });

    $(document).ready(function () {
        if ($("#createOrder").length > 0) {
            new CreateOrder({ el: $("#createOrder") });
        }
        if ($("input[name='show-blood-tubes']").val() === "no") {
            $("input:not(:checked)").closest("tr").addClass("custom-text-muted");
        }
    });

    $(document).ready(function () {
        $("[id^=order_create_kitId]").bind("cut copy paste", function (e) {
            e.preventDefault();
        });
    });
})(jQuery); // END wrapper
