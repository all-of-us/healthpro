$(document).ready(function () {
    var tableColumns = [];
    tableColumns.push(
        {name: 'created', data: 'created'},
        {name: 'participantId', data: 'participantId'},
        {name: 'user', data: 'user'},
        {name: 'site', data: 'site'},
        {name: 'dateOfService', data: 'dateOfService'},
        {name: 'occurrence', data: 'occurrence'},
        {name: 'incentiveType', data: 'incentiveType'},
        {name: 'amount', data: 'amount'},
        {name: 'declined', data: 'declined'},
        {name: 'notes', data: 'notes'},
        {name: 'type', data: 'type', orderable: false}
    );
    var url = window.location.href;
    var onSitePatientStatusTableSelector = $('#on_site_incentive_tracking');
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
        order: [[0, 'desc']],
        columnDefs: [
            {
                targets: [1],
                render: function (participantId) {
                    return '<a href="/participant/' + participantId + '">' + participantId + '</a>';
                }
            },
            {
                targets: [10],
                render: function (type) {
                    var html = '';
                    if (type === 'import_amend') {
                        html = '<span class="label label-primary">Imported</span> <span class="label label-warning">Amended</span>';
                    } else if (type === 'import') {
                        html = '<span class="label label-primary">Imported</span>';
                    } else if (type === 'amend') {
                        html = '<span class="label label-warning">Amended</span>';
                    }
                    return html;
                }
            },
            {
                targets: '_all',
                render: $.fn.dataTable.render.text()
            }
        ],
        drawCallback: function () {
            var pageInfo = table.page.info();
            $('.total-pages').text(pageInfo.pages);
            var dropDownHtml = '';
            for (var count = 1; count <= pageInfo.pages; count++) {
                var pageNumber = count - 1;
                dropDownHtml += '<option value="' + pageNumber + '">' + count + '</option>';
            }
            var pageDropDown = $('.page-drop-down select');
            pageDropDown.html(dropDownHtml);
            pageDropDown.val(pageInfo.page);
        },
    });

    $('.page-drop-down select').change(function () {
        table.page(parseInt($(this).val())).draw('page');
    });

    $('.date-filter').pmiDateTimePicker({format: 'MM/DD/YYYY', useCurrent: false});

    var formSelector = $("#incentive_tracking_filters form");
    var participantIdSelector = $('#participantId');
    var startDateSelector = $('#startDate');
    var endDateSelector = $('#endDate');
    var startDateOfServiceSelector = $('#startDateOfService');
    var endDateOfServiceSelector = $('#endDateOfService');

    var clearInvalidFields = function () {
        if (startDateSelector.parsley().validate() !== true) {
            startDateSelector.val('');
        }
        if (endDateSelector.parsley().validate() !== true) {
            endDateSelector.val('');
        }
        if (startDateOfServiceSelector.parsley().validate() !== true) {
            startDateOfServiceSelector.val('');
        }
        if (endDateOfServiceSelector.parsley().validate() !== true) {
            endDateOfServiceSelector.val('');
        }
    };

    $('#date_filter_apply').on('click', function () {
        if (startDateSelector.parsley().validate() === true && endDateSelector.parsley().validate() === true) {
            if (startDateSelector.val() !== '' || endDateSelector.val() !== '') {
                formSelector.submit();
            }
        }
    });

    $('#date_service_filter_apply').on('click', function () {
        if (startDateOfServiceSelector.parsley().validate() === true && endDateOfServiceSelector.parsley().validate() === true) {
            if (startDateOfServiceSelector.val() !== '' || endDateOfServiceSelector.val() !== '') {
                formSelector.submit();
            }
        }
    });

    $('#participant_id_filter_apply').on('click', function () {
        var isValidParticipantId = participantIdSelector.parsley().validate();
        if (isValidParticipantId === true) {
            clearInvalidFields();
            formSelector.submit();
        }
    });

    $('#participant_id_filter_reset').on('click', function () {
        participantIdSelector.val('');
        clearInvalidFields();
        formSelector.submit();
    });

    $('#date_filter_reset').on('click', function () {
        startDateSelector.val('');
        endDateSelector.val('');
        clearInvalidFields();
        formSelector.submit();
    });

    $('#date_service_filter_reset').on('click', function () {
        startDateOfServiceSelector.val('');
        endDateOfServiceSelector.val('');
        clearInvalidFields();
        formSelector.submit();
    });
});
