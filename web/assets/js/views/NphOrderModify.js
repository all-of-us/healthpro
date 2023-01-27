$(document).ready(function () {
    let showHideOtherField = function () {
        if ($(".modify-reason option:selected").val() === "OTHER") {
            $(".modify-other-text").show();
        } else {
            $(".modify-other-text").hide();
        }
    };

    showHideOtherField();

    $(".modify-reason").on("change", function () {
        showHideOtherField();
    });
});
