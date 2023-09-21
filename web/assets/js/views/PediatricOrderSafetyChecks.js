$(document).ready(function () {
    $("input").on("change", function () {
        if ($(this).data("show-target")) {
            $($(this).data("show-target")).show();
        }
        if ($(this).data("hide-target")) {
            $($(this).data("hide-target")).hide();
        }
        if ($(this).data("warning-text")) {
            if ($(this).is(":checked")) {
                $($(this).data("warning-text")).show();
            } else {
                $($(this).data("warning-text")).hide();
            }
        }
        if ($(this).data("vis-toggle")) {
            if ($(this).is(":checked")) {
                $($(this).data("vis-toggle")).show();
            } else {
                if ($("#order-info-text>span:visible").length === 0) {
                    $($(this).data("vis-toggle")).hide();
                }
            }
        }
    });
});
