function generateColvisGroups(columnGroups) {
    let colvisGroups = [];
    columnGroups.forEach(function (columnGroup) {
        let colvisGroupTemp = columnGroups.slice();
        colvisGroupTemp.splice(columnGroups.indexOf(columnGroup), 1);
        colvisGroupTemp = colvisGroupTemp.map((element) => "." + element.replaceAll(" ", "_").toLowerCase());
        colvisGroupTemp = colvisGroupTemp.join(",");
        let colvisGroup = {
            extend: "colvisGroup",
            text: columnGroup,
            show: "." + columnGroup.replaceAll(" ", "_").toLowerCase(),
            hide: colvisGroupTemp
        };
        colvisGroups.push(colvisGroup);
    });
    if (colvisGroups.length > 0) {
        colvisGroups.push({
            extend: "colvisGroup",
            text: "Show All",
            show: ":hidden"
        });
    }
    return colvisGroups;
}

function generateSortableColumns(sortableColumns) {
    let sortable = [];
    sortableColumns.forEach(function (columnIsSortable) {
        sortable.push({
            orderable: columnIsSortable
        });
    });
    return sortable;
}

$(document).ready(function () {
    let colvisGroups = generateColvisGroups($("#workqueueTable").data("colvis-groups"));
    let sortable = generateSortableColumns($("#workqueueTable").data("sortable-columns"));
    colvisGroups.push("colvis");
    let table = $("#workqueueTable").DataTable({
        ajax: "/nph/workqueue/data",
        responsive: true,
        buttons: colvisGroups,
        serverSide: true,
        columns: sortable,
        initComplete: function () {
            table.buttons().container().appendTo("#workqueueColvisContainer");
        },
        scrollX: true
    });
});
