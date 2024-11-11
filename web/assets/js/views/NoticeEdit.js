$(document).ready(function () {
    $("#notice_start_ts, #notice_end_ts").pmiDateTimePicker();
    $("#notice_url").dropdownOther({
        All: "/*",
        "In-Person Enrollment": "/ppsc/participant/p",
        "Biobank Order Pages": "/ppsc/participant/*/order/*",
        "Physical Measurements": "/ppsc/participant/*/measurements*"
    });
    $(".confirm").on("click", function () {
        return confirm("Are you sure you want to delete this notice?");
    });
});
