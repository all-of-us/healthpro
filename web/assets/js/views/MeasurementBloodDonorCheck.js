$(document).ready(function () {
    let toggleDonorType = function () {
        if ($('input[name="form[bloodDonor]"]:checked').val() !== "yes") {
            $("#blood-donor-type").addClass("d-none");
        } else {
            $("#blood-donor-type").removeClass("d-none");
        }
    };
    toggleDonorType();
    $('input[name="form[bloodDonor]"]').on("change", toggleDonorType);
});
