$(document).ready(function () {
    $("#sample_finalize_btn").on("click", function () {
        let confirmMessage = "Are you sure you want to finalize this sample?";
        return confirm(confirmMessage);
    });

    $(".add-aliquot-widget").click(function () {
        let list = $($(this).attr("data-list-selector"));
        let aliquotId = list.data("aliquot-id");
        let aliquotUnits = list.data("aliquot-units");
        let counter = list.data("widget-counter");

        // Grab the prototype template and replace the "__name__" used in the id and name of the prototype
        let newCodeWidget = list.data("code-prototype").replace(/__name__/g, counter);
        let newTsWidget = list.data("ts-prototype").replace(/__name__/g, counter);
        let newVolumeWidget = list.data("volume-prototype").replace(/__name__/g, counter);

        // Increment and update widget counter
        counter++;
        list.data("widget-counter", counter);

        let newElem = $(list.attr("data-widget-tags")).html(
            "<td>" +
                newCodeWidget +
                "</td>" +
                '<td style="position: relative">' +
                newTsWidget +
                "</td>" +
                "<td>" +
                newVolumeWidget +
                "</td>" +
                "<td style='position: relative'><span style='position: absolute; bottom: 7px; left: 0;'>" +
                aliquotUnits +
                '</span><i class="fa fa-eraser text-danger clear-aliquot-widget"' +
                ' style="position: absolute; bottom: 10px; left: 33px; font-size: 22px" role="button"></i></td>'
        );

        $(".aliquots-row-" + aliquotId)
            .last()
            .after(newElem);

        $(".order-ts").pmiDateTimePicker();
    });

    $(document).on("click", ".delete-aliquot-widget", function () {
        $(this).closest("tr").remove();
    });

    $(document).on("click", ".clear-aliquot-widget", function () {
        $(this).closest("tr").find("input").val("");
    });

    $(".aliquot-volume").keyup(function () {
        let inputValue = $(this).val();
        let minValue = $(this).data("warning-min-volume");
        let maxValue = $(this).data("warning-max-volume");
        if (inputValue && inputValue >= minValue && inputValue <= maxValue) {
            $(this).closest("tr").find(".aliquot-volume-warning").show();
        } else {
            $(this).closest("tr").find(".aliquot-volume-warning").hide();
        }
    });

    $(document).on("keyup", ".aliquot-barcode", function () {
        let barcode = $(this).val();
        let expectedBarcodeLength = $(this).data("barcode-length");
        let expectedBarcodePrefix = $(this).data("barcode-prefix");
        let regex = new RegExp(`^${expectedBarcodePrefix}\\d{${expectedBarcodeLength}}$`);
        if (regex.test(barcode)) {
            let aliquotTsSelector = $(this).closest("tr").find(".order-ts");
            aliquotTsSelector.focus();
            aliquotTsSelector.blur();
        }
    });
});
