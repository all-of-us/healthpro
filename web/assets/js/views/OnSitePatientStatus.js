$(document).ready(function () {
    var tableColumns = [];
    tableColumns.push(
        { name: "created", data: "created" },
        { name: "participantId", data: "participantId" },
        { name: "user", data: "user" },
        { name: "site", data: "site" },
        { name: "patientStatus", data: "patientStatus" },
        { name: "notes", data: "notes" },
        { name: "importId", data: "importId", orderable: false }
    );
    var url = window.location.href;
    var onSitePatientStatusTableSelector = $("#on_site_patient_status");
    var table = onSitePatientStatusTableSelector.DataTable({
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
                targets: [6],
                render: function (importId) {
                    var html = "";
                    if (importId === "Yes") {
                        html = '<span class="label label-primary">Imported</span>';
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
            generateSiteOptions();
        }
    });

    $(".page-drop-down select").change(function () {
        table.page(parseInt($(this).val())).draw("page");
    });

    $(".date-filter").pmiDateTimePicker({ format: "MM/DD/YYYY", useCurrent: false });

    var formSelector = $("#patient_status_filters form");
    var participantIdSelector = $("#participantId");
    var startDateSelector = $("#startDate");
    var endDateSelector = $("#endDate");

    var clearInvalidFields = function () {
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
        var isValidParticipantId = participantIdSelector.parsley().validate();
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

    $("#siteFilterList").on("change", "input[type=radio]", function () {
        formSelector.submit();
    });

    $("#site_filter_reset").on("click", function () {
        $("#siteFilterList input[type=radio]").first().prop("checked", true);
        formSelector.submit();
    });

    function generateSiteOptions() {
        let siteList = [];
        let jsonData = table.ajax.json().possibleSites;
        const urlParams = new URLSearchParams(window.location.search);
        for (let i = 0; i < jsonData.length; i++) {
            $("#siteFilterList").append(
                `<li class="list-group-item radio">
                        <label>
                            <input type="radio" name="site" value="${jsonData[i]["siteId"]}" ${
                    jsonData[i]["siteId"] === urlParams.get("site") ? "checked" : ""
                }>
                            ${jsonData[i]["siteName"]}
                        </label>
                    </li>`
            );
        }

        if (urlParams.get("site") === null || urlParams.get("site") === "") {
            $("#siteFilterList input[type=radio]").first().prop("checked", true);
        }
    }
});
