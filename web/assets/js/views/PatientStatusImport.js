$(document).ready(function () {
    // Ignore non-workqeue pages.
    if (!$('#patient-status-import-details').length) {
        return;
    }

    var tableColumns = [];
    tableColumns.push(
        {name: 'participantId', data: 'participantId'},
        {name: 'patientStatus', data: 'patientStatus'},
        {name: 'comments', data: 'comments'},
        {name: 'organizationName', data: 'organizationName'},
        {name: 'createdTs', data: 'createdTs'},
        {name: 'status', data: 'status'}
    );
    var url = window.location.href;
    $('table').DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        searching: false,
        ordering: false,
        ajax: {
            url: url,
            type: "POST"
        },
        columns: tableColumns,
        pageLength: 25,
        columnDefs: [
            {
                targets: [5],
                render: function (status) {
                    if (status === 1) {
                        return '<i class="fa fa-check text-success" aria-hidden="true"></i> Success';
                    } else if (status === 0) {
                        return '<i class="fa fa-tasks" aria-hidden="true"></i> In Progress';
                    } else {
                        var html = '<i class="fa fa-times text-danger" aria-hidden="true"></i> Failed';
                        if (status === 2) {
                            return html + ' <i class="fa fa-exclamation-triangle text-danger" aria-hidden="true" data-toggle="tooltip" data-container="body" data-placement="bottom" title="Invalid Participant Id"></i>';
                        }
                    }
                }
            },
            {
                targets: '_all',
                render: $.fn.dataTable.render.text()
            }
        ]
    });

    $('table').tooltip({
        selector: '[data-toggle="tooltip"]'
    });
});
