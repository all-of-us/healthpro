$(document).ready(function () {
    const tableSelector = $("table");
    let defaultSortColumn = tableSelector.data("default-sort-column");
    if (defaultSortColumn === undefined || defaultSortColumn === null) {
        defaultSortColumn = 8;
    }
    tableSelector.DataTable({
        order: [[defaultSortColumn, "desc"]],
        pageLength: 25
    });

    const dateTypes = ["created_ts", "collected_ts", "finalized_ts"];

    for (const dateType of dateTypes) {
        $("#" + dateType).html($("[data-date-type=" + dateType + "]").length);
    }
});
