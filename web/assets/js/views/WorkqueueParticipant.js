$(document).ready(function () {
    let participantInfo = $("#participant_info");
    if ($("#participant-barcode").length === 1) {
        JsBarcode("#participant-barcode", participantInfo.data("participant-id"), {
            width: 2,
            height: 50,
            displayValue: true
        });
    }
    $("#order-overflow-show").on("click", function (e) {
        $(this).hide();
        $("#order-overflow").show();
        e.preventDefault();
    });
    $("#evaluation-overflow-show").on("click", function (e) {
        $(this).hide();
        $("#evaluation-overflow").show();
        e.preventDefault();
    });
    $("#problem-overflow-show").on("click", function (e) {
        $(this).hide();
        $("#problem-overflow").show();
        e.preventDefault();
    });
});
