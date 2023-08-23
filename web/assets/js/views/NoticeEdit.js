$(document).ready(function () {
    $("#notice_start_ts, #notice_end_ts").pmiDateTimePicker();
    $("#notice_url").dropdownOther({
        All: "/*",
        "Home Page": "/",
        "Work Queue": "/workqueue/",
        "Biobank Order Pages": "/participant/*/order/*",
        "Physical Measurements": "/participant/*/measurements*",
        "Management Tools": "/access/manage/dashboard",
        "User Management": "/access/manage/user/groups"
    });
    $(".confirm").on("click", function () {
        return confirm("Are you sure you want to delete this notice?");
    });
});
