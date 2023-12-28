function generateColvisGroups(columnGroups) {
    let colvisGroups = [];
    colvisGroups.push({
        extend: "colvisGroup",
        text: "Default",
        className: "btn-outline-dark",
        show: ".default_group",
        hide: ":not(.default_group)",
        init: function (api, node) {
            $(node).removeClass("btn-secondary");
        }
    });
    columnGroups.forEach(function (columnGroup) {
        let colvisGroup = {
            extend: "colvisGroup",
            text: columnGroup,
            show: `.${columnGroup.replaceAll(" ", "_").toLowerCase()}, .show_always`,
            hide: `:not(.${columnGroup.replaceAll(" ", "_").toLowerCase()},.show_always)`,
            className: "btn-outline-dark",
            init: function (api, node) {
                $(node).removeClass("btn-secondary");
            }
        };
        colvisGroups.push(colvisGroup);
    });
    if (colvisGroups.length > 0) {
        colvisGroups.push({
            extend: "colvisGroup",
            text: "Show All",
            show: ":hidden",
            className: "btn-outline-dark",
            init: function (api, node) {
                $(node).removeClass("btn-secondary");
            }
        });
    }
    colvisGroups.push({
        extend: "colvis",
        text: "Column Visibility",
        className: "btn-outline-dark",
        init: function (api, node) {
            $(node).removeClass("btn-secondary");
        }
    });
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
    let table = $("#workqueueTable").DataTable({
        ajax: "/nph/workqueue/data",
        responsive: true,
        buttons: colvisGroups,
        serverSide: true,
        columns: sortable,
        searching: false,
        initComplete: function () {
            table.buttons().container().appendTo("#workqueueColvisContainer");
        },
        scrollX: true
    });
});
