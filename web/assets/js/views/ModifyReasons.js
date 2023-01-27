$(document).ready(function () {
    // Ignore non-modify reasons pages.
    if (!$("#form_reason").length) {
        return;
    }

    $("#form_other_text").hide();

    var selected = $("#form_reason option:selected").val();
    if (selected === "OTHER") {
        $("#form_other_text").show();
    }

    $("#form_reason").on("change", function () {
        if ($(this).val() === "OTHER") {
            $("#form_other_text").show();
        } else {
            $("#form_other_text").hide();
        }
    });
});
