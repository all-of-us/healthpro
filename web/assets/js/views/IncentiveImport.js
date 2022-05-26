$(document).ready(function () {
    $('.incentive-import-status').DataTable({
        order: [[1, 'desc']],
        pageLength: 25,
        searching: false,
        lengthChange: false,
        columnDefs: [
            {orderable: false, targets: 3}
        ]
    });

    var tableColumns = [];
    tableColumns.push(
        {name: 'participantId', data: 'participantId'},
        {name: 'userEmail', data: 'userEmail'},
        {name: 'incentiveDateGiven', data: 'incentiveDateGiven'},
        {name: 'incentiveType', data: 'incentiveType'},
        {name: 'otherIncentiveType', data: 'otherIncentiveType'},
        {name: 'incentiveOccurrence', data: 'incentiveOccurrence'},
        {name: 'otherIncentiveOccurrence', data: 'otherIncentiveOccurrence'},
        {name: 'incentiveAmount', data: 'incentiveAmount'},
        {name: 'giftCardType', data: 'giftCardType'},
        {name: 'notes', data: 'notes'},
        {name: 'declined', data: 'declined'},
        {name: 'createdTs', data: 'createdTs'},
        {name: 'status', data: 'status'}
    );
    var url = window.location.href;
    $('#incentive-import-details').DataTable({
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
                targets: [12],
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

    $('#incentive-import-details').tooltip({
        selector: '[data-toggle="tooltip"]'
    });
});
