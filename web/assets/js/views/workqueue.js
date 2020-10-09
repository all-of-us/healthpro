$(document).ready(function() {
    // Ignore non-workqeue pages.
    if (!$('#workqueue').length) {
      return;
    }

    var checkFilters = function () {
        if ($('#filters select[name=activityStatus]').val() == 'withdrawn') {
            $('#filters select').not('[name=activityStatus], [name=organization]').val('');
            $('#filters select').not('[name=activityStatus], [name=organization]').prop('disabled', true);
        } else {
            $('#filters select').prop('disabled', false);
        }
    };
    checkFilters();
    $('#filters select').on('change', function() {
        checkFilters();
        $('#filters').submit();
    });

    var exportLimit = $('#workqueue').data('export-limit');

    var workQueueExportWarningModel = function (location) {
        var exportLimitFormatted = exportLimit;
        if (window.Intl && typeof window.Intl === 'object') {
            exportLimitFormatted = new Intl.NumberFormat().format(exportLimit);
        }
        new PmiConfirmModal({
            title: 'Warning',
            msg: 'Note that the export reaches the limit of ' + exportLimitFormatted + ' participants. If your intent was to capture all participants, you may need to apply filters to ensure each export is less than ' + exportLimitFormatted + ' or utilize the Ops Data API. Please contact <em>sysadmin@pmi-ops.org</em> for more information.',
            isHTML: true,
            onTrue: function () {
                window.location = location;
            },
            btnTextTrue: 'Ok'
        });
    };

    $('button.export').on('click', function () {
        var location = $(this).data('href');
        var count = parseInt($('.count').html());
        new PmiConfirmModal({
            title: 'Attention',
            msg: 'The file you are about to download contains information that is sensitive and confidential. By clicking "accept" you agree not to distribute either the file or its contents, and to adhere to the <em>All of Us</em> Privacy and Trust Principles. A record of your acceptance will be stored at the Data and Research Center.',
            isHTML: true,
            onTrue: function () {
                if (count > exportLimit) {
                    workQueueExportWarningModel(location);
                } else {
                    window.location = location;
                }
            },
            btnTextTrue: 'Accept'
        });
    });

    var url = window.location.href;

    var surveys = $('#workqueue').data('surveys');
    var samples = $('#workqueue').data('samples');
    var isDvType = $('#workqueue').data('dv-type');

    var tableColumns = [];
    tableColumns.push(
      { name: 'lastName', data: 'lastName' },
      { name: 'firstName', data: 'firstName' },
      { name: 'middleName', data: 'middleName' },
      { name: 'dateOfBirth', data: 'dateOfBirth' },
      { name: 'participantId', visible: false, data: 'participantId' },
      { name: 'biobankId', visible: false, data: 'biobankId' },
      { name: 'language', visible: false, data: 'language', orderable: false  },
      { name: 'participantStatus', data: 'participantStatus' },
      { name: 'participantOrigin', data: 'participantOrigin', visible: !!isDvType },
      { name: 'consentCohort', data: 'consentCohort', class: 'text-center' },
      { name: 'firstPrimaryConsent', visible: false, data: 'firstPrimaryConsent', class: 'text-center' },
      { name: 'primaryConsent', data: 'primaryConsent', class: 'text-center' },
      { name: 'questionnaireOnDnaProgram', data: 'questionnaireOnDnaProgram', class: 'text-center' },
      { name: 'primaryLanguage', data: 'primaryLanguage' },
      { name: 'firstEhrConsent', visible: false, data: 'firstEhrConsent', class: 'text-center' },
      { name: 'ehrConsent', data: 'ehrConsent', class: 'text-center' },
      { name: 'ehrConsentExpireStatus', data: 'ehrConsentExpireStatus', class: 'text-center' },
      { name: 'gRoRConsent', data: 'gRoRConsent', class: 'text-center' },
      { name: 'dvEhrStatus', visible: false, data: 'dvEhrStatus', class: 'text-center' },
      { name: 'caborConsent', visible: false, data: 'caborConsent', class: 'text-center' },
      { name: 'activityStatus', data: 'activityStatus', class: 'text-center', orderable: false },
      { name: 'retentionEligibleStatus', data: 'retentionEligibleStatus', class: 'text-center' },
      { name: 'retentionType', data: 'retentionType', class: 'text-center', orderable: false },
      { name: 'withdrawalReason', visible: false, data: 'withdrawalReason', class: 'text-center' },
      { name: 'patientStatusYes', visible: false, data: 'patientStatusYes', orderable: false },
      { name: 'patientStatusNo', visible: false, data: 'patientStatusNo', orderable: false },
      { name: 'patientStatusUnknown', visible: false, data: 'patientStatusUnknown', orderable: false },
      { name: 'patientStatusNoAccess', visible: false, data: 'patientStatusNoAccess', orderable: false },
      { name: 'contactMethod', visible: false, data: 'contactMethod', orderable: false },
      { name: 'address', visible: false, data: 'address'},
      { name: 'email', visible: false, data: 'email' },
      { name: 'loginPhone', visible: false, data: 'loginPhone' },
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
        processing: true,
        serverSide: true,
        scrollX: true,
        ajax: {
            url: url,
            type: "POST"
        },
        order: [[11, 'desc']],
        dom: 'lBrtip',
        columns: tableColumns,
        pageLength: 25,
        createdRow: function(row, data) {
            if (data.isWithdrawn === true) {
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
                    '.col-group-demographics',
                    '.col-group-contact',
                    '.col-group-patient-status'
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
                    '.col-group-demographics',
                    '.col-group-contact',
                    '.col-group-patient-status'
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
                    '.col-group-demographics',
                    '.col-group-contact',
                    '.col-group-patient-status'
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
                    '.col-group-demographics',
                    '.col-group-contact',
                    '.col-group-patient-status'
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
                    '.col-group-ppi',
                    '.col-group-ppi-time',
                    '.col-group-contact',
                    '.col-group-patient-status'
                ]
            },
            {
                extend: 'colvisGroup',
                text: 'Patient Status',
                show: [
                    'dateOfBirth:name',
                    '.col-group-default',
                    '.col-group-patient-status'
                ],
                hide: [
                    '.col-group-demographics',
                    '.col-group-info:not(.col-group-default)',
                    '.col-group-inperson',
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
                    '.col-group-ppi',
                    '.col-group-ppi-time',
                    '.col-group-patient-status'
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

    table.buttons().container().find('.btn').addClass('btn-sm');
    $('#workqueue_length').addClass('pull-right');
    $('#workqueue_info').addClass('pull-left');

    // Display custom error message
    $.fn.dataTable.ext.errMode = 'none';
    $('#workqueue').on('error.dt', function(e) {
        alert('An error occurred please reload the page and try again');
    });

    // Scroll to top when performing pagination
    $('#workqueue').on('page.dt', function() {
        //Took reference from https://stackoverflow.com/a/21627503
        $('html').animate({
            scrollTop: $('#filters').offset().top
        }, 'slow');
        $('thead tr th:first-child').trigger('focus').trigger('blur');
    });
});
