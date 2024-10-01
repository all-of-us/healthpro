$(document).ready(function () {
    $("input#dlw_participantWeight").on("change", function () {
        calculateDose();
    });

    $("#enter_pound").on("click", function () {
        $("#pound_modal").modal("show");
    });

    $("#confirm_btn").on("click", function () {
        let poundInput = $("#modal_pound_input");
        let weight = poundInput.val();
        poundInput.val("");
        weight = (weight / 2.2046).toFixed(1);
        $("input#dlw_participantWeight").val(weight);
        let dosage = weight * 1.5;
        $("input#dlw_calculatedDose").val(dosage.toFixed(1));
        $("#pound_modal").modal("hide");
    });

    $("#form_edit").on("click", function () {
        $("input[readonly=readonly]").prop("readonly", "");
        $("#dlw_calculatedDose").prop("readonly", "readonly");
        $("#form_edit").replaceWith('<button type="submit" class="btn btn-primary" id="form_submit">Save</button>');
    });

    $("input").on("change", function () {
        $(this).siblings(".help-block").remove();
    });

    function calculateDose() {
        let weight = $("input#dlw_participantWeight").val();
        let dosage = weight * 1.5;
        $("input#dlw_calculatedDose").val(dosage.toFixed(1));
    }

    calculateDose();
});
