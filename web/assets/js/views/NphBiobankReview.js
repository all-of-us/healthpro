$(document).ready(function () {
    const tableSelector = $("table");
    const exportTableType = tableSelector.data("export-table-type");
    const defaultSortColumn = tableSelector.data("default-sort-column") ?? 8;
    const currentDate = new Date().toISOString().split("T")[0];
    let reviewTable = tableSelector.DataTable({
        order: [[defaultSortColumn, "desc"]],
        pageLength: 25,
        buttons: [
            {
                extend: "csv",
                text: "Export CSV",
                filename: `${exportTableType}_${currentDate}`,
                exportOptions: {
                    modifier: {
                        search: "none",
                        order: "applied"
                    },
                    columns: ":visible",
                    format: {
                        body: function (data, row, column, node) {
                            let dataRowValue = $(node).data("row");
                            if (dataRowValue !== undefined && dataRowValue !== null) {
                                return String(dataRowValue).replace(/\s+/g, " ").trim();
                            }
                            return data.replace(/\s+/g, " ").trim();
                        },
                        header: function (data, column, node) {
                            // Remove badge or other HTML elements inside the header for export
                            let modifiedHeader = $(node).data("header") || data;
                            return modifiedHeader.replace(/\s+/g, " ").trim();
                        }
                    }
                },
                visible: false
            }
        ]
    });

    reviewTable.button(".buttons-csv").nodes().hide();

    const dateTypes = ["created_ts", "collected_ts", "finalized_ts"];

    for (const dateType of dateTypes) {
        $("#" + dateType).html($("[data-date-type=" + dateType + "]").length);
    }

    $("#review_today_filter_start_date, #review_today_filter_end_date").pmiDateTimePicker({ format: "MM/DD/YYYY" });

    const checkCellData = (cells) =>
        cells.map((cell) => {
            const dataRowValue = cell.getAttribute("data-row");
            let cellData =
                dataRowValue !== null
                    ? dataRowValue.replace(/\s+/g, " ").trim()
                    : cell.textContent.replace(/\s+/g, " ").trim();
            // Escape commas and double quotes for CSV
            if (cellData.includes(",") || cellData.includes('"')) {
                cellData = `"${cellData.replace(/"/g, '""')}"`;
            }
            return cellData;
        });

    const generateCSV = (exportType) => {
        const csv = [];

        const headerRow = [];
        const $headers = $("table thead th");
        $headers.each((index, th) => {
            const $th = $(th);
            const headerText = $th.data("header");
            if (headerText) {
                headerRow.push(headerText);
            }
        });
        csv.push(headerRow.join(","));

        const $rows = $("table tbody tr");

        $rows.each((index, row) => {
            if ($(row).hasClass("sf-ajax-request")) {
                return;
            }
            const rowData = [];
            const cells = [...row.querySelectorAll("td")];
            const rowDataEntry = [];
            rowDataEntry.push(...checkCellData(cells));
            rowData.push(rowDataEntry.join(","));
            csv.push(rowData.join(","));
        });
        const csvContent = csv.join("\n");

        // Create a download link and trigger the download
        const link = document.createElement("a");
        link.href = `data:text/csv;charset=utf-8,${encodeURI(csvContent)}`;
        link.target = "_blank";
        link.download = `${exportType}_${currentDate}.csv`;
        link.click();
    };

    const showExportModal = (exportAction) => {
        new PmiConfirmModal({
            title: "Attention",
            msg: 'The file you are about to download contains information that is sensitive and confidential. By clicking "accept" you agree not to distribute either the file or its contents, and to adhere to the <em>All of Us</em> Privacy and Trust Principles. A record of your acceptance will be stored at the Data and Research Center.',
            isHTML: true,
            onTrue: () => {
                $.ajax({
                    url: "/nph/biobank/review/export/log",
                    method: "GET",
                    data: { exportType: exportTableType },
                    error: (xhr, status, error) => {
                        console.error("AJAX request failed:", status, error);
                    }
                });
                exportAction();
            },
            btnTextTrue: "Accept"
        });
    };

    $("#review_export_all").on("click", () => {
        showExportModal(() => {
            reviewTable.button(".buttons-csv").trigger();
        });
    });

    $("#review_export").on("click", () => {
        showExportModal(() => {
            generateCSV(exportTableType);
        });
    });
});
