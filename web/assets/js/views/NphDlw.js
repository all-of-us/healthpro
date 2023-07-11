$(document).ready(function () {
    $("input#dlw_participantWeight").on("change", function () {
        let weight = $(this).val();
        let dosage = weight * 1.5;
        $("input#dlw_calculatedDose").val(dosage.toFixed(0));
    });
});
