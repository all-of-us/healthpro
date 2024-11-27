$(document).ready(function () {
    const tableSelector = $("table");
    let defaultSortColumn = tableSelector.data("default-sort-column") ?? 8;
    tableSelector.DataTable({
        order: [[defaultSortColumn, "desc"]],
        pageLength: 25
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
                cellContent = `${cellContent} (${labelTexts})`.trim();
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
        const currentDate = new Date();
        const datePart = currentDate.toISOString().split("T")[0].replace(/-/g, "");
        const timePart = currentDate.toISOString().split("T")[1].slice(0, 8).replace(":", "");
        const formattedDate = `${datePart}-${timePart}`;
        link.download = `${exportType}_${formattedDate}.csv`;
        link.click();
    };

    $(".export").on("click", function () {
        const exportType = $(this).data("export-type");
        const exportCategory = $(this).data("export-category");
        new PmiConfirmModal({
            title: "Attention",
            msg: 'The file you are about to download contains information that is sensitive and confidential. By clicking "accept" you agree not to distribute either the file or its contents, and to adhere to the <em>All of Us</em> Privacy and Trust Principles. A record of your acceptance will be stored at the Data and Research Center.',
            isHTML: true,
            onTrue: function () {
                if (exportCategory === "all") {
                    window.location.href = "/nph/biobank/review/export?exportType=" + exportType;
                } else {
                    generateCSV(exportType);
                }
            },
            btnTextTrue: "Accept"
        });
    });
});
