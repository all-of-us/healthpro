$(document).ready(function () {
    // Ignore non-workqeue pages.
    if (!$("#workqueue").length) {
        return;
    }

    var buttonGroups = {
        Default: "default",
        Consent: "consent",
        "PPI Surveys": "surveys",
        "In-Person": "enrollment",
        Demographics: "demographics",
        "Patient Status": "status",
        Contact: "contact",
        Metrics: "metrics",
        "Ancillary Studies": "ancillary",
        "Show all": "all"
    };

    var url = window.location.href;

    var wQColumns = $("#workqueue").data("wq-columns");
    var columnsDef = $("#workqueue").data("columns-def");
    var isDvType = $("#workqueue").data("dv-type");
    var viewId = $("#workqueue").data("view-id");

    var tableColumns = [];

    var generateTableRow = function (field, columnDef) {
        var row = {};
        row.name = field;
        row.data = field;
        if (columnDef.hasOwnProperty("htmlClass")) {
            row.class = columnDef["htmlClass"];
        }
        if (columnDef.hasOwnProperty("orderable")) {
            row.orderable = columnDef["orderable"];
        }
        if (columnDef.hasOwnProperty("visible")) {
            row.visible = columnDef["visible"];
        }
        if (columnDef.hasOwnProperty("checkDvVisibility")) {
            row.visible = !!isDvType;
        }
        tableColumns.push(row);
    };

    wQColumns.forEach(function (field) {
        var columnDef = columnsDef[field];
        if (columnDef.hasOwnProperty("names")) {
            Object.keys(columnDef["names"]).forEach(function (key) {
                generateTableRow(key + "Consent", columnDef);
            });
        } else {
            generateTableRow(field, columnDef);
        }
    });

    var table = $("#workqueue").DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        ajax: {
            url: url,
            type: "POST"
        },
        order: [[13, "desc"]],
        dom: "lBrtip",
        columns: tableColumns,
        pageLength: 25,
        drawCallback: function () {
            var pageInfo = table.page.info();
            $(".total-pages").text(pageInfo.pages);
            var dropDownHtml = "";
            for (var count = 1; count <= pageInfo.pages; count++) {
                var pageNumber = count - 1;
                dropDownHtml += '<option value="' + pageNumber + '">' + count + "</option>";
            }
            var pageDropDown = $(".page-drop-down select");
            pageDropDown.html(dropDownHtml);
            pageDropDown.val(pageInfo.page);
            $('[data-toggle="tooltip"]').tooltip();
        },
        createdRow: function (row, data) {
            if (data.isWithdrawn === true) {
                $(row).addClass("tr-withdrawn");
            }
        },
        buttons: [
            {
                extend: "colvisGroup",
                text: "Default",
                show: [".col-group-default"],
                hide: [
                    ".col-group-info:not(.col-group-default)",
                    ".col-group-ppi:not(.col-group-default)",
                    ".col-group-inperson:not(.col-group-default)",
                    ".col-group-demographics",
                    ".col-group-contact",
                    ".col-group-patient-status",
                    ".col-group-metrics",
                    ".col-group-ancillarystudies"
                ]
            },
            {
                extend: "colvisGroup",
                text: "Consent",
                show: [".col-group-consent"],
                hide: [
                    ".col-group-info:not(.col-group-consent)",
                    ".col-group-default:not(.col-group-consent)",
                    ".col-group-ppi",
                    ".col-group-inperson:not(.col-group-default)",
                    ".col-group-demographics",
                    ".col-group-contact",
                    ".col-group-patient-status",
                    ".col-group-metrics",
                    ".col-group-ancillarystudies"
                ]
            },
            {
                extend: "colvisGroup",
                text: "PPI Surveys",
                show: ["dateOfBirth:name", ".col-group-ppi"],
                hide: [
                    ".col-group-info",
                    ".col-group-inperson",
                    ".col-group-demographics",
                    ".col-group-contact",
                    ".col-group-patient-status",
                    ".col-group-metrics",
                    ".col-group-ancillarystudies"
                ]
            },
            {
                extend: "colvisGroup",
                text: "In-Person",
                show: ["dateOfBirth:name", ".col-group-inperson"],
                hide: [
                    ".col-group-info",
                    ".col-group-ppi",
                    ".col-group-demographics",
                    ".col-group-contact",
                    ".col-group-patient-status",
                    ".col-group-metrics",
                    ".col-group-ancillarystudies"
                ]
            },
            {
                extend: "colvisGroup",
                text: "Demographics",
                show: ["dateOfBirth:name", ".col-group-demographics"],
                hide: [
                    ".col-group-info",
                    ".col-group-inperson",
                    ".col-group-ppi",
                    ".col-group-contact",
                    ".col-group-patient-status",
                    ".col-group-metrics",
                    ".col-group-ancillarystudies"
                ]
            },
            {
                extend: "colvisGroup",
                text: "Patient Status",
                show: [
                    "dateOfBirth:name",
                    ".col-group-default",
                    ".col-group-patient-status",
                    ".col-group-ehr-expire-status",
                    ".col-group-metrics-ehr"
                ],
                hide: [
                    ".col-group-demographics",
                    ".col-group-info:not(.col-group-default, .col-group-ehr-expire-status)",
                    ".col-group-inperson",
                    ".col-group-ppi",
                    ".col-group-contact",
                    ".col-group-metrics:not(.col-group-metrics-ehr)",
                    ".col-group-ancillarystudies",
                    ".col-group-pediatric"
                ]
            },
            {
                extend: "colvisGroup",
                text: "Contact",
                show: ["dateOfBirth:name", ".col-group-default", ".col-group-contact", ".col-group-retention"],
                hide: [
                    ".col-group-demographics",
                    ".col-group-info:not(.col-group-default)",
                    ".col-group-inperson",
                    ".col-group-ppi",
                    ".col-group-patient-status",
                    ".col-group-metrics:not(.col-group-retention)",
                    ".col-group-consent-cohort",
                    ".col-group-program-update",
                    ".col-group-language-primary-consent",
                    ".col-group-ehr-expire-status",
                    ".col-group-consent",
                    ".col-group-ancillarystudies",
                    ".col-group-pediatric"
                ]
            },
            {
                extend: "colvisGroup",
                text: "Metrics",
                show: ["dateOfBirth:name", ".col-group-default", ".col-group-ehr-expire-status", ".col-group-metrics"],
                hide: [
                    ".col-group-demographics",
                    ".col-group-info:not(.col-group-default, .col-group-ehr-expire-status)",
                    ".col-group-inperson",
                    ".col-group-ppi",
                    ".col-group-patient-status",
                    ".col-group-contact",
                    ".col-group-ancillarystudies",
                    ".col-group-pediatric"
                ]
            },
            {
                extend: "colvisGroup",
                text: "Ancillary Studies",
                show: ["dateOfBirth:name", ".col-group-default", ".col-group-ancillarystudies"],
                hide: [
                    ".col-group-demographics",
                    ".col-group-info:not(.col-group-default, .col-group-ehr-expire-status)",
                    ".col-group-inperson",
                    ".col-group-ppi",
                    ".col-group-patient-status",
                    ".col-group-contact",
                    ".col-group-metrics",
                    ".col-group-ehr-expire-status",
                    ".col-group-consent",
                    ".col-group-pediatric"
                ]
            },
            {
                extend: "colvisGroup",
                text: "Show all",
                show: ":hidden"
            }
        ]
    });

    // Hide filter buttons in customized WQ views
    if (viewId) {
        table.buttons().nodes().addClass("hidden");
    }

    $(".page-drop-down select").change(function () {
        table.page(parseInt($(this).val())).draw("page");
    });

    // Populate count in header
    $("#workqueue").on("init.dt", function (e, settings, json) {
        var count = json.recordsFiltered;
        $("#heading-count .count").text(count);
        if (count == 1) {
            $("#heading-count .plural").hide();
        } else {
            $("#heading-count .plural").show();
        }
        $("#heading-count").show();
    });

    table.buttons().container().find(".btn").addClass("btn-sm");
    $("#workqueue_info").addClass("pull-left");

    // Display custom error message
    $.fn.dataTable.ext.errMode = "none";
    $("#workqueue").on("error.dt", function (e) {
        alert("An error occurred please reload the page and try again");
    });

    // Scroll to top when performing pagination
    $("#workqueue").on("page.dt", function () {
        //Took reference from https://stackoverflow.com/a/21627503
        $("html").animate(
            {
                scrollTop: $("#filters").offset().top
            },
            "slow"
        );
        $("thead tr th:first-child").trigger("focus").trigger("blur");
    });

    var columnsUrl = $("#columns_group").data("columns-url");

    let disableViewButtons = function () {
        $(".view-btn").addClass("disabled");
    };

    let enableViewButtons = function () {
        $(".view-btn").removeClass("disabled");
    };

    let setColumnNames = function (params) {
        disableViewButtons();
        $.ajax({
            url: columnsUrl,
            data: params
        })
            .done(function () {
                enableViewButtons();
            })
            .fail(function () {
                enableViewButtons();
            });
    };

    $(".toggle-vis").on("click", function () {
        var column = table.column($(this).attr("data-column"));
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
            var column = table.column($(this).attr("data-column"));
            column.visible($(this).prop("checked"));
        });
    };

    toggleColumns();

    var showColumns = function () {
        var columns = table.columns();
        columns.visible(true);
    };

    var hideColumns = function () {
        for (let i = 3; i <= wQColumns.length + 1; i++) {
            var column = table.column(i);
            column.visible(false);
        }
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

    // Check/uncheck columns when clicked on group buttons
    $("#workqueue").on("column-visibility.dt", function (e, settings, column, state) {
        if (column >= 3) {
            $("#toggle_column_" + column).prop("checked", state);
        }
    });

    // Handle button groups
    table.on("buttons-action", function (e, buttonApi) {
        var groupName = buttonGroups[buttonApi.text()];
        var params;
        if (groupName === "all") {
            params = { select: true };
        } else {
            params = { groupName: groupName };
        }
        $.get(columnsUrl, params);
    });
});
