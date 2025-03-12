$(document).ready(function () {
    const startEndDates = ["notice_start_ts", "notice_end_ts"];

    startEndDates.forEach(id => {
        const element = document.querySelector(`#${id}`);
        bs5DateTimepicker(element, {
            clock: true,
            sideBySide: true,
            useCurrent: true
        });
    });

    let urlOptions = {
        All: "/*",
        "In-Person Enrollment": "/ppsc/participant/p",
        "Biobank Order Pages": "/ppsc/participant/*/order/*",
        "Physical Measurements": "/ppsc/participant/*/measurements*"
    };
    if ($('form[name="notice"]').data("route") === "nph_") {
        urlOptions = {
            All: "/nph/*",
            "Home Page": "/nph",
            "Biospecimen Lookup": "/nph/orders",
            "Participant Summary": "/nph/participant/p",
            "Aliquot Samples": "/nph/samples/aliquot",
            "Management Tools": "/access/manage/dashboard",
            "User Management": "/access/manage/user/groups"
        };
    }
    $("#notice_url").dropdownOther(urlOptions);
    $("#notice_nph_url").dropdownOther({
        All: "/*",
        "In-Person": "/ppsc/participant/p",
        "Biobank Order Pages": "/ppsc/participant/*/order/*",
        "Physical Measurements": "/ppsc/participant/*/measurements*"
    });
    $(".confirm").on("click", function () {
        return confirm("Are you sure you want to delete this notice?");
    });
});
