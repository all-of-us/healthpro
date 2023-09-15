$(document).ready(function () {
    $("#sample_finalize_btn").on("click", function (e) {
        e.preventDefault();
        $("#confirmation_modal").modal("show");
    });

    $("#confirm_finalize_btn").on("click", function () {
        $("#confirmation_modal").modal("hide");
        $("form[name='nph_sample_finalize']").submit();
    });

    $(document).on("click", ".add-aliquot-widget", function () {
        if ($(this).data("aliquot-code") === "SALIVAA2") {
            addGlycerolAliquotRow(this);
        } else {
            addNormalAliquotRow(this);
        }
    });

    $(document).on("click", ".delete-aliquot-widget", function () {
        $(this).closest("tr").remove();
    });

    $(document).on("click", ".clear-aliquot-widget", function () {
        $(this).closest("tr").find("input:not(:read-only)").val("");
    });

    $(document).on("keyup", ".aliquot-volume", function () {
        let inputValue = parseFloat($(this).val());
        let minValue = $(this).data("warning-min-volume");
        let maxValue = $(this).data("warning-max-volume");
        if (inputValue && inputValue >= minValue && inputValue <= maxValue) {
            if ($(this).data("warning-target")) {
                $("#" + $(this).data("warning-target")).show();
            }
            $(this).closest("tr").find(".aliquot-volume-warning").show();
        } else {
            if ($(this).data("warning-target")) {
                $("#" + $(this).data("warning-target")).hide();
            }
            $(this).closest("tr").find(".aliquot-volume-warning").hide();
        }
    });

    $(document).on("keyup", ".glycerol-volume", function () {
        calculateGlycerolVolume(
            $(this).closest("tr").find(".sample"),
            $(this).closest("tr").find(".additive"),
            $(this).closest("tr").data("sample-index")
        );
    });

    $(document).on("keyup", ".aliquot-barcode", function () {
        let barcode = $(this).val();
        let expectedBarcodeLength = $(this).data("barcode-length");
        let expectedBarcodePrefix = $(this).data("barcode-prefix");
        let regex = new RegExp(`^${expectedBarcodePrefix}\\d{${expectedBarcodeLength}}$`);
        if (regex.test(barcode)) {
            let aliquotTsSelector = $(this).closest("tr").find(".order-ts");
            aliquotTsSelector.focus();
            aliquotTsSelector.data("DateTimePicker").date(new Date());
            aliquotTsSelector.blur();
            $(this).closest("tr").find(".aliquot-volume").focus();
        }
    });

    let disableEnableAliquotFields = function () {
        let $checkboxes = $(".sample-modify-checkbox:checkbox:enabled");
        $checkboxes.each(function () {
            let $row = $(this).closest("tr");
            $row.find(".order-ts").prop("readonly", $(this).is(":checked"));
            if ($row.find(".aliquot-volume").data("expected-volume")) {
            }
        });
    };

    function calculateGlycerolVolume(sampleVolumeField, glycerolVolumeField, index) {
        let sampleVolume = $(sampleVolumeField).val() ? parseFloat($(sampleVolumeField).val()) * 1000 : 0;
        let glycerolVolume = $(glycerolVolumeField).val() ? parseFloat($(glycerolVolumeField).val()) : 0;
        let totalVolume = ((sampleVolume + glycerolVolume) / 1000).toFixed(2);
        $(`#totalVol${index}`).val(`${totalVolume}`);
    }

    function addNormalAliquotRow(element) {
        let list = $($(element).attr("data-list-selector"));
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
                '</span><i class="fa fa-eraser text-danger clear-aliquot-widget"\' +\n' +
                '                \' style="position: absolute; bottom: 10px; left: 33px; font-size: 22px" role="button"></i></td>'
        );

        $(".aliquots-row-" + aliquotId)
            .last()
            .after(newElem);

        $(".order-ts").pmiDateTimePicker({
            maxDate: new Date().setHours(23, 59, 59, 999)
        });
    }

    function addGlycerolAliquotRow(element) {
        let list = $($(element).attr("data-list-selector"));
        let aliquotId = list.data("aliquot-id");
        let aliquotUnits = list.data("aliquot-units");
        let counter = list.data("widget-counter");
        let aliquotCode = $(element).data("aliquot-code");
        let rows = $(".duplicate-target-" + aliquotCode).clone();
        rows.each(function () {
            let barcodeName = `nph_sample_finalize[SALIVAA2][${counter}]`;
            let tsName = `nph_sample_finalize[SALIVAA2AliquotTs][${counter}]`;
            let volumeName = `nph_sample_finalize[SALIVAA2Volume][${counter}]`;
            let glycerolVolumeName = `nph_sample_finalize[SALIVAA2glycerolAdditiveVolume][${counter}]`;
            $(this).removeClass("duplicate-target-" + aliquotCode);
            $(this).find("[name='nph_sample_finalize[SALIVAA2][0]']").attr("name", barcodeName);
            $(this).find("[name='nph_sample_finalize[SALIVAA2AliquotTs][0]']").attr("name", tsName);
            $(this).find("[name='nph_sample_finalize[SALIVAA2Volume][0]']").attr("name", volumeName);
            $(this)
                .find("[name='nph_sample_finalize[SALIVAA2glycerolAdditiveVolume][0]']")
                .attr("name", glycerolVolumeName);
            $(this).find("input").val("");
            $(this).find("input:not(.totalVol)").attr("readonly", false);
        });
        counter++;
        list.data("widget-counter", counter);
        $(element).closest("tr").before(rows);
        $(".order-ts").pmiDateTimePicker({
            maxDate: new Date().setHours(23, 59, 59, 999)
        });
    }

    disableEnableAliquotFields();
    calculateGlycerolVolume(
        $("#nph_sample_finalize_SALIVAA2Volume_0"),
        $("#nph_sample_finalize_SALIVAA2glycerolAdditiveVolume")
    );
    $(".aliquot-volume").trigger("keyup");

    $(".sample-modify-checkbox").on("change", disableEnableAliquotFields);
});
