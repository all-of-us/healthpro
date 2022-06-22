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

    $('#date_filter_apply').on('click', function () {
        var formSelector = $("#patient_status_filters form");
        formSelector.parsley().validate();
        if (formSelector.parsley().isValid()) {
            if ($('#startDate').val() !== '' || $('#endDate').val() !== '') {
                formSelector.submit();
            }
        }
    });
});
