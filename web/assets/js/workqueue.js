$(document).ready(function() {
    // Ignore non-workqeue pages.
    if (!$('#workqueue').length) {
      return;
    }

    var checkFilters = function () {
        if ($('select[name=withdrawalStatus]').val() == 'NO_USE') {
            $('select').not('[name=withdrawalStatus], [name=organization]').val('');
            $('select').not('[name=withdrawalStatus], [name=organization]').prop('disabled', true);
        } else {
            $('select').prop('disabled', false);
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
      { name: 'language', visible: false, data: 'language'  },
      { name: 'participantStatus', data: 'participantStatus' },
      { name: 'generalConsent', data: 'generalConsent' },
      { name: 'ehrConsent', data: 'ehrConsent' },
      { name: 'caborConsent', visible: false, data: 'caborConsent' },
      { name: 'withdrawal', data: 'withdrawal' },
      { name: 'contactMethod', visible: false, data: 'contactMethod' },
      { name: 'address', visible: false, data: 'address'},
      { name: 'email', visible: false, data: 'email' },
      { name: 'phone', visible: false, data: 'phone' },
      { name: 'ppiStatus', data: 'ppiStatus' },
      { name: 'ppiSurveys', data: 'ppiSurveys' }
    );
    Object.keys(surveys).forEach(function(key, _i) {
      tableColumns.push(
        { name: 'ppi'+key, visibile: false, data: 'ppi'+key }
      );
      tableColumns.push(
        { name: 'ppi'+key+'Time', visibile: false, data: 'ppi'+key+'Time' }
      );
    });
    tableColumns.push(
      { name: 'pairedSiteLocation', data: 'pairedSiteLocation' },
      { name: 'physicalMeasurementsStatus', data: 'physicalMeasurementsStatus' },
      { name: 'evaluationFinalizedSite', visible: false, data: 'evaluationFinalizedSite' },
      { name: 'biobankDnaStatus', data: 'biobankDnaStatus' },
      { name: 'biobankSamples', data: 'biobankSamples'}
    );
    Object.keys(samples).forEach(function(key, _i) {
      tableColumns.push(
        { name: 'sample'+key, visibile: false, data: 'sample'+key }
      );
      tableColumns.push(
        { name: 'sample'+key+'Time', visibile: false, data: 'sample'+key+'Time' }
      );
    });
    tableColumns.push(
      { name: 'orderCreatedSite', visible: false, data: 'orderCreatedSite' },
      { name: 'age', visible: false, data: 'age' },
      { name: 'sex', visible: false, data: 'sex' },
      { name: 'genderIdentity', visible: false, data: 'genderIdentity' },
      { name: 'race', visible: false, data: 'race' },
      { name: 'education', visible: false, data: 'education' }
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
        columnDefs: [
            { className: "text-center", targets: [6, 7, 8, 9, 14, 15, 16, 18, 20, 22, 24, 26, 28, 30, 31, 32, 34, 36, 38, 40, 42, 44, 46, 48] }
        ],
        pageLength: 25,
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

    //Reset table data on page length change
    $('#workqueue').on('length.dt', function() {
        table.clear().draw();
    });

    table.buttons().container().find('.btn').addClass('btn-sm');
    $('#workqueue_length').addClass('pull-right');
    $('#workqueue_info').addClass('pull-left');
});
