$(document).ready(function () {
    // Ignore non-workqeue pages.
    if (!$('#workqueue_consents').length) {
        return;
    }

    var columnsDef = $('#workqueue_consents').data('columns-def');
    var consentColumns = $('#workqueue_consents').data('wq-columns');

    var tableColumns = [];

    var generateTableRow = function (field, columnDef) {
        var row = {};
        row.name = field;
        row.data = field;
        if (columnDef.hasOwnProperty('htmlClass')) {
            row.class = columnDef['htmlClass'];
        }
        if (columnDef.hasOwnProperty('orderable')) {
            row.class = columnDef['orderable'];
        }
        tableColumns.push(row);
    };

    consentColumns.forEach(function (field) {
        var columnDef = columnsDef[field];
        if (columnDef.hasOwnProperty('names')) {
            Object.keys(columnDef['names']).forEach(function (key) {
                generateTableRow(key + 'Consent', columnDef);
            });
        } else {
            generateTableRow(field, columnDef);
        }
    });

    var url = window.location.href;

    var workQueueTable = $('#workqueue_consents').DataTable({
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
        drawCallback: function () {
            var pageInfo = workQueueTable.page.info();
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
        createdRow: function (row, data) {
            if (data.isWithdrawn === true) {
                $(row).addClass('tr-withdrawn');
            }
        }
    });

    $('.page-drop-down select').change(function (e) {
        workQueueTable.page(parseInt($(this).val())).draw('page');
    });

    var showColumns = function () {
        var columns = workQueueTable.columns();
        columns.visible(true);
    };

    var hideColumns = function () {
        for (let i = 5; i <= 16; i++) {
            var column = workQueueTable.column(i);
            column.visible(false);
        }
    };

    $('#columns_select_all').on('click', function () {
        $('#columns_group input[type=checkbox]').prop('checked', true);
        showColumns();
        $.get("/workqueue/consent/columns", {select: true});
    });

    $('#columns_deselect_all').on('click', function () {
        $('#columns_group input[type=checkbox]').prop('checked', false);
        hideColumns();
        $.get("/workqueue/consent/columns", {deselect: true});
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

    // Populate count in header
    $('#workqueue_consents').on('init.dt', function (e, settings, json) {
        var count = json.recordsFiltered;
        $('#heading-count .count').text(count);
        if (count == 1) {
            $('#heading-count .plural').hide();
        } else {
            $('#heading-count .plural').show();
        }
        $('#heading-count').show();
    });

    $('#workqueue_info').addClass('pull-left');

    // Display custom error message
    $.fn.dataTable.ext.errMode = 'none';
    $('#workqueue_consents').on('error.dt', function (e) {
        alert('An error occurred please reload the page and try again');
    });

    // Scroll to top when performing pagination
    $('#workqueue_consents').on('page.dt', function () {
        //Took reference from https://stackoverflow.com/a/21627503
        $('html').animate({
            scrollTop: $('#filters').offset().top
        }, 'slow');
        $('thead tr th:first-child').trigger('focus').trigger('blur');
    });

    $('.toggle-vis').on('click', function () {
        var column = workQueueTable.column($(this).attr('data-column'));
        column.visible(!column.visible());
        var columnName = $(this).data('name');
        // Set column names in session
        var consentColumnsUrl = $('#columns_group').data('consent-columns-url');
        $.get(consentColumnsUrl, {columnName: columnName, checked: $(this).prop('checked')});
    });

    var toggleColumns = function () {
        $('#columns_group input[type=checkbox]').each(function () {
            var column = workQueueTable.column($(this).attr('data-column'));
            column.visible($(this).prop('checked'));
        });
    };

    toggleColumns();
});
