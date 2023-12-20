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

$(document).ready(function () {
    let colvisGroups = generateColvisGroups($("#workqueueTable").data("colvis-groups"));
    colvisGroups.push("colvis");
    let table = $("#workqueueTable").DataTable({
        ajax: "/nph/workqueue/data",
        responsive: true,
        buttons: colvisGroups,
        initComplete: function () {
            table.buttons().container().appendTo("#workqueueTable-wrapper");
        }
    });
});
