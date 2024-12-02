$(document).ready(function () {
    const dateTypes = [
        "measurement_created_ts",
        "measurement_finalized_ts",
        "order_created_ts",
        "order_collected_ts",
        "order_processed_ts",
        "order_finalized_ts"
    ];

    // Display total count for each step in the date column headers
    for (const dateType of dateTypes) {
        $("#" + dateType).html($("[data-date-type=" + dateType + "]").length);
    }

    $(
        "#form_start_date, #form_end_date, #review_today_filter_start_date, #review_today_filter_end_date"
    ).pmiDateTimePicker({ format: "MM/DD/YYYY" });

    const escapeCSVValue = (value) => {
        if (value.includes(",") || value.includes('"')) {
            return `"${value.replace(/"/g, '""')}"`;
        }
        return value;
    };

    const checkCellData = (cells) =>
        cells.map((cell) => {
            const cellContent = cell.textContent.trim();
            if (cell.getAttribute("data-column-type") === "on_site") {
                const cellSpan = cell.querySelector("span[data-on-site-date]");
                return cellSpan ? cellSpan.getAttribute("data-on-site-date") : "";
            }
            return cellContent;
        });

    const generateCSV = () => {
        const csv = [];
        const headerRow = [
            "Participant ID",
            "Name",
            "PM Status",
            "PM Created",
            "PM Finalized",
            "Biobank Order Status",
            "Biobank ID",
            "Order ID",
            "Biobank Order Created By",
            "Biobank Order Created",
            "Biobank Order Collected",
            "Biobank Order Processed",
            "Biobank Order Finalized",
            "Biobank Order Finalized Samples"
        ];
        csv.push(headerRow.join(","));

        const $rows = $("table tbody tr");

        let participantId = null;
        let participantName = null;

        $rows.each((index, row) => {
            if ($(row).hasClass("sf-ajax-request")) {
                return;
            }
            const rowData = [];
            const cells = [...row.querySelectorAll("td")];
            const rowDataEntry = [];
            if (cells[0].hasAttribute("data-participant-id")) {
                participantId = cells[0].textContent.trim();
                participantName = cells[1].textContent.trim();
            }
            rowDataEntry.push(participantId, escapeCSVValue(participantName));
            if (cells[0].hasAttribute("data-participant-id")) {
                rowDataEntry.push(...checkCellData(cells.slice(2)));
            } else {
                rowDataEntry.push(...checkCellData(cells));
            }
            rowData.push(rowDataEntry.join(","));
            csv.push(rowData.join(","));
        });
        const csvContent = csv.join("\n");

        // Create a download link and trigger the download
        const link = document.createElement("a");
        link.href = `data:text/csv;charset=utf-8,${encodeURI(csvContent)}`;
        link.target = "_blank";
        const currentDate = new Date().toISOString().split("T")[0];
        link.download = `TodaysParticipants_${currentDate}.csv`;
        link.click();
    };

    $("#export_btn").on("click", function () {
        new PmiConfirmModal({
            title: "Attention",
            msg: 'The file you are about to download contains information that is sensitive and confidential. By clicking "accept" you agree not to distribute either the file or its contents, and to adhere to the <em>All of Us</em> Privacy and Trust Principles. A record of your acceptance will be stored at the Data and Research Center.',
            isHTML: true,
            onTrue: function () {
                generateCSV();
            },
            btnTextTrue: "Accept"
        });
    });
});
