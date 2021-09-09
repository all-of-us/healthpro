$(document).ready(function() {
    // Ignore non-workqeue pages.
    if (!$('#workqueue_consents').length) {
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

    var exportLimit = $('#workqueue_consents').data('export-limit');

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

    var digitalHealthSharingTypes = $('#workqueue_consents').data('digital-health-sharing-types');

    var tableColumns = [];
    tableColumns.push(
        { name: 'lastName', data: 'lastName' },
        { name: 'firstName', data: 'firstName' },
        { name: 'middleName', data: 'middleName' },
        { name: 'dateOfBirth', data: 'dateOfBirth' },
        { name: 'participantId', data: 'participantId' },
        { name: 'primaryConsent', data: 'primaryConsent', class: 'text-center' },
        { name: 'questionnaireOnDnaProgram', data: 'questionnaireOnDnaProgram', class: 'text-center' },
        { name: 'ehrConsent', data: 'ehrConsent', class: 'text-center' },
        { name: 'ehrConsentExpireStatus', data: 'ehrConsentExpireStatus', class: 'text-center' },
        { name: 'gRoRConsent', data: 'gRoRConsent', class: 'text-center' },
        { name: 'dvEhrStatus', data: 'dvEhrStatus', class: 'text-center' },
        { name: 'caborConsent', data: 'caborConsent', class: 'text-center' }
    );
    Object.keys(digitalHealthSharingTypes).forEach(function (key, _i) {
        tableColumns.push(
            {name: key + 'Consent', data: key + 'Consent', class: 'text-center', orderable: false}
        );
    });
    tableColumns.push(
        { name: 'consentCohort', data: 'consentCohort', class: 'text-center' },
        { name: 'primaryLanguage', data: 'primaryLanguage' },
    );

    $('#workqueue_consents').DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        ajax: {
            url: url,
            type: "POST"
        },
        order: [[5, 'desc']],
        dom: 'lrtip',
        columns: tableColumns,
        pageLength: 25,
        createdRow: function(row, data) {
            if (data.isWithdrawn === true) {
                $(row).addClass('tr-withdrawn');
            }
        }
    });

    // Populate count in header
    $('#workqueue_consents').on('init.dt', function(e, settings, json) {
        var count = json.recordsFiltered;
        $('#heading-count .count').text(count);
        if (count == 1) {
            $('#heading-count .plural').hide();
        } else {
            $('#heading-count .plural').show();
        }
        $('#heading-count').show();
    });

    $('#workqueue_length').addClass('pull-right');
    $('#workqueue_info').addClass('pull-left');

    // Display custom error message
    $.fn.dataTable.ext.errMode = 'none';
    $('#workqueue_consents').on('error.dt', function(e) {
        alert('An error occurred please reload the page and try again');
    });

    // Scroll to top when performing pagination
    $('#workqueue_consents').on('page.dt', function() {
        //Took reference from https://stackoverflow.com/a/21627503
        $('html').animate({
            scrollTop: $('#filters').offset().top
        }, 'slow');
        $('thead tr th:first-child').trigger('focus').trigger('blur');
    });
});
