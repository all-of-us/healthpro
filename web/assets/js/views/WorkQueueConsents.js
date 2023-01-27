$(document).ready(function () {
    // Ignore non-workqeue pages.
    if (!$("#workqueue_consents").length) {
        return;
    }

    var columnsDef = $("#workqueue_consents").data("columns-def");
    var consentColumns = $("#workqueue_consents").data("wq-columns");

    var tableColumns = [];

    var generateTableRow = function (field, columnDef) {
        var row = {};
        row.name = field;
        row.data = field;
        if (columnDef.hasOwnProperty("htmlClass")) {
            row.class = columnDef["htmlClass"];
        }
        if (columnDef.hasOwnProperty("orderable")) {
            row.class = columnDef["orderable"];
        }
        tableColumns.push(row);
    };

    consentColumns.forEach(function (field) {
        var columnDef = columnsDef[field];
        if (columnDef.hasOwnProperty("names")) {
            Object.keys(columnDef["names"]).forEach(function (key) {
                generateTableRow(key + "Consent", columnDef);
            });
        } else {
            generateTableRow(field, columnDef);
        }
    });

    var url = window.location.href;

    var workQueueTable = $("#workqueue_consents").DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        ajax: {
            url: url,
            type: "POST"
        },
        order: [[5, "desc"]],
        dom: "lrtip",
        columns: tableColumns,
        pageLength: 25,
        drawCallback: function () {
            var pageInfo = workQueueTable.page.info();
            $(".total-pages").text(pageInfo.pages);
            var dropDownHtml = "";
            for (var count = 1; count <= pageInfo.pages; count++) {
                var pageNumber = count - 1;
                dropDownHtml += '<option value="' + pageNumber + '">' + count + "</option>";
            }
            var pageDropDown = $(".page-drop-down select");
            pageDropDown.html(dropDownHtml);
            pageDropDown.val(pageInfo.page);
        },
        createdRow: function (row, data) {
            if (data.isWithdrawn === true) {
                $(row).addClass("tr-withdrawn");
            }
        }
    });

    $(".page-drop-down select").change(function (e) {
        workQueueTable.page(parseInt($(this).val())).draw("page");
    });

    var showColumns = function () {
        var columns = workQueueTable.columns();
        columns.visible(true);
    };

    var hideColumns = function () {
        for (let i = 5; i <= 16; i++) {
            var column = workQueueTable.column(i);
            column.visible(false);
        }
    };

    let consentColumnsUrl = $("#columns_group").data("consent-columns-url");

    let disableViewButtons = function () {
        $(".view-btn").addClass("disabled");
    };

    let enableViewButtons = function () {
        $(".view-btn").removeClass("disabled");
    };

    let setColumnNames = function (params) {
        disableViewButtons();
        $.ajax({
            url: consentColumnsUrl,
            data: params
        })
            .done(function () {
                enableViewButtons();
            })
            .fail(function () {
                enableViewButtons();
            });
    };

    $("#columns_select_all").on("click", function () {
        $("#columns_group input[type=checkbox]").prop("checked", true);
        showColumns();
        let params = {
            select: true
        };
        setColumnNames(params);
    });

    $("#columns_deselect_all").on("click", function () {
        $("#columns_group input[type=checkbox]").prop("checked", false);
        hideColumns();
        let params = {
            deselect: true
        };
        setColumnNames(params);
    });

    // Populate count in header
    $("#workqueue_consents").on("init.dt", function (e, settings, json) {
        var count = json.recordsFiltered;
        $("#heading-count .count").text(count);
        if (count == 1) {
            $("#heading-count .plural").hide();
        } else {
            $("#heading-count .plural").show();
        }
        $("#heading-count").show();
    });

    $("#workqueue_info").addClass("pull-left");

    // Display custom error message
    $.fn.dataTable.ext.errMode = "none";
    $("#workqueue_consents").on("error.dt", function (e) {
        alert("An error occurred please reload the page and try again");
    });

    // Scroll to top when performing pagination
    $("#workqueue_consents").on("page.dt", function () {
        //Took reference from https://stackoverflow.com/a/21627503
        $("html").animate(
            {
                scrollTop: $("#filters").offset().top
            },
            "slow"
        );
        $("thead tr th:first-child").trigger("focus").trigger("blur");
    });

    $(".toggle-vis").on("click", function () {
        var column = workQueueTable.column($(this).attr("data-column"));
        column.visible(!column.visible());
        var columnName = $(this).data("name");
        // Set column names in session
        let params = {
            columnName: columnName,
            checked: $(this).prop("checked")
        };
        setColumnNames(params);
    });

    var toggleColumns = function () {
        $("#columns_group input[type=checkbox]").each(function () {
            var column = workQueueTable.column($(this).attr("data-column"));
            column.visible($(this).prop("checked"));
        });
    };

    toggleColumns();
});
