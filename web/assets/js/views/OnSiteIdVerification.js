$(document).ready(function () {
    let tableColumns = [];
    tableColumns.push(
        { name: "created", data: "created" },
        { name: "participantId", data: "participantId" },
        { name: "user", data: "user" },
        { name: "verificationType", data: "verificationType" },
        { name: "visitType", data: "visitType" },
        { name: "type", data: "type", orderable: false }
    );
    let url = window.location.href;
    let onSitePatientStatusTableSelector = $("#on_site_id_verification");
    let table = onSitePatientStatusTableSelector.DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        searching: false,
        ajax: {
            url: url,
            type: "POST"
        },
        columns: tableColumns,
        pageLength: 25,
        order: [[0, "desc"]],
        columnDefs: [
            {
                targets: [1],
                render: function (participantId) {
                    return '<a href="/participant/' + participantId + '">' + participantId + "</a>";
                }
            },
            {
                targets: [5],
                render: function (type, display, row) {
                    let html = "";
                    if (type === "import") {
                        html = '<span class="label label-primary">Imported</span>';
                    } else if (row["guardianVerified"]) {
                        html = '<span class="label label-primary"><i class="fa fa-check"/> Guardian</span>';
                    }
                    return html;
                }
            },
            {
                targets: "_all",
                render: $.fn.dataTable.render.text()
            }
        ],
        drawCallback: function () {
            let pageInfo = table.page.info();
            $(".total-pages").text(pageInfo.pages);
            let dropDownHtml = "";
            for (let count = 1; count <= pageInfo.pages; count++) {
                let pageNumber = count - 1;
                dropDownHtml += '<option value="' + pageNumber + '">' + count + "</option>";
            }
            let pageDropDown = $(".page-drop-down select");
            pageDropDown.html(dropDownHtml);
            pageDropDown.val(pageInfo.page);
        }
    });

    $(".page-drop-down select").change(function () {
        table.page(parseInt($(this).val())).draw("page");
    });

    $(".date-filter").pmiDateTimePicker({ format: "MM/DD/YYYY", useCurrent: false });

    let formSelector = $("#id_verification_filters form");
    let participantIdSelector = $("#participantId");
    let startDateSelector = $("#startDate");
    let endDateSelector = $("#endDate");

    let clearInvalidFields = function () {
        if (startDateSelector.parsley().validate() !== true) {
            startDateSelector.val("");
        }
        if (endDateSelector.parsley().validate() !== true) {
            endDateSelector.val("");
        }
    };

    $("#date_filter_apply").on("click", function () {
        if (startDateSelector.parsley().validate() === true && endDateSelector.parsley().validate() === true) {
            if (startDateSelector.val() !== "" || endDateSelector.val() !== "") {
                formSelector.submit();
            }
        }
    });

    $("#participant_id_filter_apply").on("click", function () {
        let isValidParticipantId = participantIdSelector.parsley().validate();
        if (isValidParticipantId === true) {
            clearInvalidFields();
            formSelector.submit();
        }
    });

    $("#participant_id_filter_reset").on("click", function () {
        participantIdSelector.val("");
        clearInvalidFields();
        formSelector.submit();
    });

    $("#date_filter_reset").on("click", function () {
        startDateSelector.val("");
        endDateSelector.val("");
        clearInvalidFields();
        formSelector.submit();
    });
});
