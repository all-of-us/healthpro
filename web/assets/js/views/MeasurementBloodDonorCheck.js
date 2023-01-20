$(document).ready(function () {
    var toggleDonorType = function () {
        if ($('input[name="form[bloodDonor]"]:checked').val() !== "yes") {
            $("#blood-donor-type").addClass("hidden");
        } else {
            $("#blood-donor-type").removeClass("hidden");
        }
    };
    toggleDonorType();
    $('input[name="form[bloodDonor]"]').on("change", toggleDonorType);
});
