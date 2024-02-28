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
    $(".filter-value").each(function () {
        let column = $(this).data("filter-field");
        let filterVal = $(this).data("filter-value");
        if (filterVal !== "") {
            search[column] = filterVal;
        }
    });
    return search;
}

function removeFilter(element) {
    $(element).parent().parent().parent().remove();
    let appliedFiltersElement = $("#applied-filters");
    if ($("#applied-filters-area").children().length === 0) {
        appliedFiltersElement.addClass("d-none");
    }
}

function redrawTable() {
    $("#workqueueTable").DataTable().draw();
}

function createFilterElementText(element) {
    let textElement = $(element).parent().siblings().find("input");
    let appliedFiltersArea = $("#applied-filters-area");
    let appliedFiltersElement = $("#applied-filters");
    let column = textElement.data("field");
    let columnDisplay = textElement.data("column-display-name");
    let columnGroup = textElement.data("columngroup");
    let filterVal = textElement.val();
    textElement.val("");
    $(`#filter-${column}`).addClass("d-none");
    let columnGroupElement = $(`#filter-group-${columnGroup}`);
    if ($(`.filter-column-group-${columnGroup}:not(.d-none)`).length === 0) {
        columnGroupElement.addClass("d-none");
    }
    appliedFiltersElement.removeClass("d-none");
    if ($(`#filter-value-${column}`).length > 0) {
        $(`#filter-value-${column}`).remove();
    }
    let newElement = appliedFiltersElement.append(
        `<div id="filter-value-${column}" data-filter-field="${column}" data-filter-value="${filterVal}" class="filter-value col-sm-1">
            <div class="card">
                <div>
                    <button class="btn-close text-danger position-absolute top-0 end-0 m-2 filter-remove"></button>
                </div>
                <div class="card-header">
                    <label>${columnDisplay}</label>
                </div>
                <div class="card-body">
                  <label class="">${filterVal}</label>
                </div>
            </div>
        </div>`
    );
    newElement.find(".filter-remove").on("click", function () {
        removeFilter($(this));
        redrawTable();
    });
    redrawTable();
}

function triggerExport(type = "full") {
    let search = generateSearch();
    let columns = [];
    if (type === "full") {
        columns = [];
    } else {
        columns = $("#workqueueTable").DataTable().columns().visible();
    }
    console.dir(search);
    console.dir(columns);
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

    $(".filter-dismiss").on("click", function () {
        let column = $(this).data("column");
        let columnGroup = $(this).data("columngroup");
        let columnGroupElement = $(`#filter-group-${columnGroup}`);
        $(`#filter-${column}`).addClass("d-none");
        if ($(`.filter-column-group-${columnGroup}:not(.d-none)`).length === 0) {
            columnGroupElement.addClass("d-none");
        }
    });

    $(".filter-show").on("click", function () {
        let column = $(this).data("column");
        let columnGroup = $(this).data("columngroup");
        $(`#filter-${column}`).removeClass("d-none");
        $(`#filter-group-${columnGroup}`).removeClass("d-none");
    });

    $(".apply-filter-button").on("click", function () {
        if ($(this).data("filter-type") == "text") {
            createFilterElementText($(this));
        }
    });

    $(".date-picker").each(function () {
        const maxDate = new Date();
        maxDate.setHours(23, 59, 59, 999);
        bs5DateTimepicker(this, {
            format: "MM/dd/yyyy",
            maxDate: maxDate,
            clock: false
        });
    });
});
