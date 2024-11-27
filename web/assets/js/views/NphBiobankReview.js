$(document).ready(function () {
    const tableSelector = $("table");
    const exportTableType = tableSelector.data("export-table-type");
    const defaultSortColumn = tableSelector.data("default-sort-column") ?? 8;
    const currentDate = new Date().toISOString().split("T")[0];
    let reviewTable = tableSelector.DataTable({
        order: [[defaultSortColumn, "desc"]],
        pageLength: 25,
        dom: "lBfrtip",
        buttons: [
            {
                extend: "csv",
                text: "Export CSV",
                filename: `${exportTableType}_${currentDate}`,
                exportOptions: {
                    modifier: {
                        search: "none" // This ensures that all rows, including those hidden by search, are included in the export
                    },
                    columns: ":visible",
                    format: {
                        // body: function (data, row, column, node) {
                        //     // Remove leading/trailing spaces and replace inner multiple spaces with a single space for all data
                        //     return data.trim();
                        // },
                        header: function (data, column, node) {
                            // Remove badge or other HTML elements inside the header for export
                            let modifiedHeader = $(node).data("header") || data;
                            return modifiedHeader.trim();
                        }
                    }
                },
                visible: false
            }
        ]
    });

    reviewTable.button(".buttons-csv").nodes().hide();

    $("#review_export_all").on("click", function () {
        reviewTable.button(".buttons-csv").trigger(); // Trigger CSV export manually
    });

    const dateTypes = ["created_ts", "collected_ts", "finalized_ts"];

    for (const dateType of dateTypes) {
        $("#" + dateType).html($("[data-date-type=" + dateType + "]").length);
    }

    $("#review_today_filter_start_date, #review_today_filter_end_date").pmiDateTimePicker({ format: "MM/DD/YYYY" });

    const checkCellData = (cells) =>
        cells.map((cell) => {
            // Extract the text from <label> elements if they exist
            const labelTexts = Array.from(cell.querySelectorAll("label"))
                .map((label) => label.textContent.trim())
                .join(" ");

            // Get the cell's main text content (excluding <label> elements)
            const clonedCell = cell.cloneNode(true);
            clonedCell.querySelectorAll("label").forEach((label) => label.remove());
            let cellContent = clonedCell.textContent.trim();

            // Combine cell content with label text
            if (labelTexts) {
                cellContent = `${cellContent} ${labelTexts}`.trim();
            }

            if (cell.getAttribute("data-order-id")) {
                cellContent = cell.getAttribute("data-order-id");
            }
            return cellContent;
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

    $("#review_export").on("click", function () {
        const exportType = $(this).data("export-type");
        new PmiConfirmModal({
            title: "Attention",
            msg: 'The file you are about to download contains information that is sensitive and confidential. By clicking "accept" you agree not to distribute either the file or its contents, and to adhere to the <em>All of Us</em> Privacy and Trust Principles. A record of your acceptance will be stored at the Data and Research Center.',
            isHTML: true,
            onTrue: function () {
                generateCSV(exportType);
            },
            btnTextTrue: "Accept"
        });
    });
});
