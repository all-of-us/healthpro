$(document).ready(function () {
    var tableColumns = [];
    tableColumns.push(
        {name: 'created', data: 'created'},
        {name: 'participantId', data: 'participantId'},
        {name: 'user', data: 'user'},
        {name: 'site', data: 'site'},
        {name: 'patientStatus', data: 'patientStatus'},
        {name: 'notes', data: 'notes'},
        {name: 'importId', data: 'importId', orderable: false}
    );
    var url = window.location.href;
    var onSitePatientStatusTableSelector = $('#on_site_patient_status');
    onSitePatientStatusTableSelector.DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        searching: false,
        bLengthChange: false,
        ajax: {
            url: url,
            type: "POST"
        },
        columns: tableColumns,
        pageLength: 25,
        order: [[0, 'desc']],
        columnDefs: [
            {
                targets: [1],
                render: function (participantId) {
                    return '<a href="/participant/' + participantId + '">' + participantId + '</a>';
                }
            },
            {
                targets: [6],
                render: function (importId) {
                    var html = '';
                    if (importId === 'Yes') {
                        html = '<span class="label label-primary">Imported</span>';
                    }
                    return html;
                }
            },
            {
                targets: '_all',
                render: $.fn.dataTable.render.text()
            }
        ]
    });

    $('.date-filter').pmiDateTimePicker({format: 'MM/DD/YYYY', useCurrent: false});

    var formSelector = $("#patient_status_filters form");
    var startDateSelector = $('#startDate');
    var endDateSelector = $('#endDate');

    var clearInvalidFields = function () {
        if (startDateSelector.parsley().validate() !== true) {
            startDateSelector.val('');
        }
        if (endDateSelector.parsley().validate() !== true) {
            endDateSelector.val('');
        }
    };

    $('#date_filter_apply').on('click', function () {
        if (startDateSelector.parsley().validate() === true && endDateSelector.parsley().validate() === true) {
            if (startDateSelector.val() !== '' || startDateSelector.val() !== '') {
                formSelector.submit();
            }
        }
    });

    $('#participant_id_filter_apply').on('click', function () {
        var isValidParticipantId = $('#participantId').parsley().validate();
        if (isValidParticipantId === true) {
            clearInvalidFields();
            formSelector.submit();
        }
    });
});
