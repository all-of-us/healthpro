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

    $("#modify_check_all").on("change", function () {
        $(".modify-samples input:checkbox:enabled").prop("checked", $(this).prop("checked"));
    });

    $("input:checkbox").on("change", function () {
        let allSamplesChecked = $(".modify-samples :checkbox:not(:checked)").length === 0;
        $("#modify_check_all").prop("checked", allSamplesChecked);
    });
});
