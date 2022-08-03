$(document).ready(function () {
    var exportLimit = $('.workqueue-table').data('export-limit');

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

    $('tbody').on( 'click', 'td .view-consent-histories', function () {
        let consentModal = $('#consentModal');
        let modelContent = $("#consentModal .modal-content");
        modelContent.html('');
        modelContent.load(
            $(this).attr('data-href')
        );
        $(consentModal).modal('show');
    });
});
