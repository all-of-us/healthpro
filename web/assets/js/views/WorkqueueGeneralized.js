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

function generateColumns(columns, sortableColumns) {
    let sortable = [];
    columns.forEach(function (columnName, index) {
        sortable.push({
            orderable: sortableColumns[index],
            name: columnName
        });
    });
    return sortable;
}

function generateSearch() {
    let search = {};
    $(".wq-text-input").each(function () {
        let filter = $(this).val();
        let column = $(this).data("field");
        search[column] = filter;
    });

    $(".wq-filter-radio").each(function () {
        let column = $(this).data("name");
        let filter = $("input[name='" + column + "']:checked").val();
        search[column] = filter;
    });
    return search;
}

$(document).ready(function () {
    let colvisGroups = generateColvisGroups($("#workqueueTable").data("colvis-groups"));
    let columns = generateColumns(
        $("#workqueueTable").data("column-names"),
        $("#workqueueTable").data("sortable-columns")
    );
    let table = $("#workqueueTable").DataTable({
        ajax: {
            url: "/nph/workqueue/data",
            data: function (data, settings, json) {
                return $.extend({}, data, {
                    search: generateSearch()
                });
            }
        },
        responsive: true,
        buttons: colvisGroups,
        serverSide: true,
        columns: columns,
        searching: false,
        processing: true,
        searchable: false,
        initComplete: function () {
            table.buttons().container().appendTo("#workqueueColvisContainer");
            table.button("0").trigger();
        },
        scrollX: true
    });
});
