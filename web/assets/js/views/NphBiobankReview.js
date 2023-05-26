$(document).ready(function () {
    $('table').DataTable({
        order: [[9, 'desc']],
        pageLength: 25
    });

    const dateTypes = [
        "created_ts",
        "collected_ts",
        "finalized_ts"
    ];

    for (const dateType of dateTypes) {
        $("#" + dateType).html($("[data-date-type=" + dateType + "]").length);
    }
});
