$(document).ready(function() {
    // Ignore non-workqeue pages.
    if (!$('#workqueue').length) {
      return;
    }

    var checkFilters = function () {
        if ($('#filters select[name=withdrawalStatus]').val() == 'NO_USE') {
            $('#filters select').not('[name=withdrawalStatus], [name=organization]').val('');
            $('#filters select').not('[name=withdrawalStatus], [name=organization]').prop('disabled', true);
        } else {
            $('#filters select').prop('disabled', false);
        }
    };
    checkFilters();
    $('#filters select').change(function() {
        checkFilters();
        $('#filters').submit();
    });
    $('button.export').click(function() {
        var location = $(this).data('href');
        new PmiConfirmModal({
            title: 'Attention',
            msg: 'The file you are about to download contains information that is sensitive and confidential. By clicking "accept" you agree not to distribute either the file or its contents, and to adhere to the <em>All of Us</em> Privacy and Trust Principles. A record of your acceptance will be stored at the Data and Research Center.',
            isHTML: true,
            onTrue: function() {
                window.location = location;
            },
            btnTextTrue: 'Accept'
        });
    });
    var url = window.location.href;

    var surveys = $('#workqueue').data('surveys');
    var samples = $('#workqueue').data('samples');

    var tableColumns = [];
    tableColumns.push(
      { name: 'lastName', data: 'lastName' },
      { name: 'firstName', data: 'firstName' },
      { name: 'dateOfBirth', data: 'dateOfBirth' },
      { name: 'participantId', visible: false, data: 'participantId' },
      { name: 'biobankId', visible: false, data: 'biobankId' },
      { name: 'language', visible: false, data: 'language', orderable: false  },
      { name: 'participantStatus', data: 'participantStatus' },
      { name: 'generalConsent', data: 'generalConsent', class: 'text-center' },
      { name: 'ehrConsent', data: 'ehrConsent', class: 'text-center' },
      { name: 'caborConsent', visible: false, data: 'caborConsent', class: 'text-center' },
      { name: 'withdrawal', data: 'withdrawal', class: 'text-center' },
      { name: 'contactMethod', visible: false, data: 'contactMethod', orderable: false },
      { name: 'address', visible: false, data: 'address'},
      { name: 'email', visible: false, data: 'email' },
      { name: 'phone', visible: false, data: 'phone' },
      { name: 'ppiStatus', data: 'ppiStatus', class: 'text-center' },
      { name: 'ppiSurveys', data: 'ppiSurveys', class: 'text-center' }
    );
    Object.keys(surveys).forEach(function(key, _i) {
      tableColumns.push(
        { name: 'ppi'+key, visible: false, data: 'ppi'+key, class: 'text-center' }
      );
      tableColumns.push(
        { name: 'ppi'+key+'Time', visible: false, data: 'ppi'+key+'Time' }
      );
    });
    tableColumns.push(
      { name: 'pairedSite', data: 'pairedSite' },
      { name: 'pairedOrganization', data: 'pairedOrganization' },
      { name: 'physicalMeasurementsStatus', data: 'physicalMeasurementsStatus', class: 'text-center' },
      { name: 'evaluationFinalizedSite', visible: false, data: 'evaluationFinalizedSite', orderable: false },
      { name: 'biobankDnaStatus', data: 'biobankDnaStatus', class: 'text-center' },
      { name: 'biobankSamples', data: 'biobankSamples', class: 'text-center'}
    );
    Object.keys(samples).forEach(function(key, _i) {
      tableColumns.push(
        { name: 'sample'+key, visible: false, data: 'sample'+key, class: 'text-center' }
      );
      tableColumns.push(
        { name: 'sample'+key+'Time', visible: false, data: 'sample'+key+'Time' }
      );
    });
    tableColumns.push(
      { name: 'orderCreatedSite', visible: false, data: 'orderCreatedSite', orderable: false },
      { name: 'age', visible: false, data: 'age' },
      { name: 'sex', visible: false, data: 'sex', orderable: false },
      { name: 'genderIdentity', visible: false, data: 'genderIdentity' },
      { name: 'race', visible: false, data: 'race' },
      { name: 'education', visible: false, data: 'education', orderable: false }
    );

    var table = $('#workqueue').DataTable({
        pagingType: "simple",
        processing: true,
        serverSide: true,
        scrollX: true,
        ajax: {
            url: url,
            type: "POST"
        },
        order: [[7, 'desc']],
        dom: 'lBrtip',
        columns: tableColumns,
        pageLength: 25,
        createdRow: function(row, data) {
            if (data.withdrawal) {
                $(row).addClass('tr-withdrawn');
            }
        },
        buttons: [
            {
                extend: 'colvisGroup',
                text: 'Default',
                show: [
                    '.col-group-default'
                ],
                hide: [
                    '.col-group-info:not(.col-group-default)',
                    '.col-group-ppi:not(.col-group-default)',
                    '.col-group-ppi-time',
                    '.col-group-inperson:not(.col-group-default)',
                    '.col-group-inperson-time',
                    '.col-group-demographics',
                    '.col-group-contact'
                ]
            },
            {
                extend: 'colvisGroup',
                text: 'PPI Surveys',
                show: [
                    'dateOfBirth:name',
                    '.col-group-ppi'
                ],
                hide: [
                    '.col-group-info',
                    '.col-group-ppi-time',
                    '.col-group-inperson',
                    '.col-group-inperson-time',
                    '.col-group-demographics',
                    '.col-group-contact'
                ]
            },
            {
                extend: 'colvisGroup',
                text: 'PPI Surveys + Dates',
                show: [
                    '.col-group-ppi',
                    '.col-group-ppi-time'
                ],
                hide: [
                    'dateOfBirth:name',
                    '.col-group-info',
                    '.col-group-inperson',
                    '.col-group-inperson-time',
                    '.col-group-demographics',
                    '.col-group-contact'
                ]
            },
            {
                extend: 'colvisGroup',
                text: 'In-Person',
                show: [
                    'dateOfBirth:name',
                    '.col-group-inperson'
                ],
                hide: [
                    '.col-group-info',
                    '.col-group-ppi',
                    '.col-group-ppi-time',
                    '.col-group-inperson-time',
                    '.col-group-demographics',
                    '.col-group-contact'
                ]
            },
            {
                extend: 'colvisGroup',
                text: 'In-Person + Sample Dates',
                show: [
                    '.col-group-inperson',
                    '.col-group-inperson-time'
                ],
                hide: [
                    'dateOfBirth:name',
                    '.col-group-info',
                    '.col-group-ppi',
                    '.col-group-ppi-time',
                    '.col-group-demographics',
                    '.col-group-contact'
                ]
            },
            {
                extend: 'colvisGroup',
                text: 'Demographics',
                show: [
                    'dateOfBirth:name',
                    '.col-group-demographics'
                ],
                hide: [
                    '.col-group-info',
                    '.col-group-inperson',
                    '.col-group-inperson-time',
                    '.col-group-ppi',
                    '.col-group-ppi-time',
                    '.col-group-contact'
                ]
            },
            {
                extend: 'colvisGroup',
                text: 'Contact',
                show: [
                    'dateOfBirth:name',
                    '.col-group-default',
                    '.col-group-contact'
                ],
                hide: [
                    '.col-group-demographics',
                    '.col-group-info:not(.col-group-default)',
                    '.col-group-inperson',
                    '.col-group-inperson-time',
                    '.col-group-ppi',
                    '.col-group-ppi-time'
                ]
            },
            {
                extend: 'colvisGroup',
                text: 'Show all',
                show: ':hidden'
            },
            {
                extend: 'colvis',
                text: 'Columns <i class="fa fa-caret-down" aria-hidden="true"></i>',
                columns: ':not(.col-group-name)'
            }
        ]
    });

    // Populate count in header
    $('#workqueue').on('init.dt', function(e, settings, json) {
        var count = json.recordsFiltered;
        $('#heading-count .count').text(count);
        if (count == 1) {
            $('#heading-count .plural').hide();
        } else {
            $('#heading-count .plural').show();
        }
        $('#heading-count').show();
    });

    // Reset table data on page length change
    $('#workqueue').on('length.dt', function() {
        table.clear().draw();
    });

    table.buttons().container().find('.btn').addClass('btn-sm');
    $('#workqueue_length').addClass('pull-right');
    $('#workqueue_info').addClass('pull-left');

    // Display custom error message
    $.fn.dataTable.ext.errMode = 'none';
    $('#workqueue').on('error.dt', function(e) {
        alert('An error occured please reload the page and try again');
    });

    // Disable pagination buttons when ajax request is made
    $('#workqueue').on('preXhr.dt', function(e) {
        $('.paginate_button').addClass('disabled');
    });
});
