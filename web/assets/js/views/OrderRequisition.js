$(document).ready(function () {
    $("#requisition-loaded").hide();
    if ($('iframe[name="requisition"]')[0].contentWindow.document.readyState === "complete") {
        $("#requisition-loading").hide();
        $("#requisition-loaded").show();
    }
    $("iframe[name=requisition]").on("load", function () {
        try {
            if (typeof window.requisition.print === "function") {
                $("#requisition-loading").hide();
                $("#requisition-loaded").show();
            } else {
                $("#requisition-loading-widget").hide();
            }
        } catch (e) {
            // catch firefox issue where pdf.js plugin makes the pdf frame cross-origin
            // https://github.com/mozilla/pdf.js/issues/5397
            $("#requisition-loading-widget").hide();
        }
    });
    new PMI.views["OrderSubPage"]({
        el: $("body")
    });
});
