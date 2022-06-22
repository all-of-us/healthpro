$(document).ready(function () {
    var tableColumns = [];
    tableColumns.push(
        {name: 'created', data: 'created'},
        {name: 'participantId', data: 'participantId'},
        {name: 'user', data: 'user'},
        {name: 'site', data: 'site'},
        {name: 'patientStatus', data: 'patientStatus'},
        {name: 'notes', data: 'notes'}
    );
    var url = window.location.href;
    var onSitePatientStatusTableSelector = $('#on_site_patient_status');
    onSitePatientStatusTableSelector.DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        searching: false,
        ordering: false,
        bLengthChange: false,
        ajax: {
            url: url,
            type: "POST"
        },
        columns: tableColumns,
        pageLength: 25,
        columnDefs: [
            {
                targets: '_all',
                render: $.fn.dataTable.render.text()
            }
        ]
    });

    $('.date-filter').pmiDateTimePicker({format: 'MM/DD/YYYY', useCurrent: false});

    var formSelector = $("#patient_status_filters form");

    $('#date_filter_apply').on('click', function () {
        var startDateSelector = $('#startDate');
        var endDateSelector = $('#endDate');
        var isValidStartDate = startDateSelector.parsley().validate();
        var isValidEndDate = endDateSelector.parsley().validate();
        if (isValidStartDate === true && isValidEndDate === true) {
            if (startDateSelector.val() !== '' || startDateSelector.val() !== '') {
                formSelector.submit();
            }
        }
    });

    $('#participant_id_filter_apply').on('click', function () {
        var isValidParticipantId = $('#participantId').parsley().validate();
        if (isValidParticipantId === true) {
            formSelector.submit();
        }
    });
});
